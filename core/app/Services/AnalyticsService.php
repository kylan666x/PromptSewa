<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Prompt;
use App\Models\PromptDailyStat;
use App\Models\PromptReport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * G5 (v1.7.0) — view counting + dashboard series.
 *
 * Views are STATS, not money: prompt_daily_stats upserts are allowed here
 * (UNIQUE(prompt, day)); the wallet ledger's insert-only rule is untouched.
 *
 * Dedupe: once per session-hour per prompt (session key), owner/staff
 * excluded — staff previews and self-views never inflate the count.
 *
 * Series are file-cached 10 min (TTL honest; invalidate-on-write not
 * required). Empty series is a first-class state: every builder returns a
 * dense 30-point array of zeros when nothing happened.
 */
class AnalyticsService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /**
     * Count a detail-page view. Returns true when counted, false when
     * deduped (or excluded).
     */
    public function recordView(Request $request, Prompt $prompt): bool
    {
        $viewer = $request->user();

        // Owner and staff views never count.
        if ($viewer !== null && ($viewer->id === $prompt->user_id || $viewer->isModerator())) {
            return false;
        }

        // Once per session-hour per prompt.
        $key = 'viewed:'.$prompt->id;
        $session = $request->session();
        $last = $session->get($key);

        if ($last !== null && CarbonImmutable::parse($last)->greaterThan(now()->subHour())) {
            return false;
        }

        $session->put($key, now()->toIso8601String());

        // Upsert the daily stat row + increment the counter — stats writes,
        // allowed (unlike the ledger). Two writes, no transaction needed:
        // both are idempotent-ish counters, never financial truth.
        PromptDailyStat::query()->upsert(
            ['prompt_id' => $prompt->id, 'day' => now()->toDateString(), 'views' => 1],
            ['prompt_id', 'day'],
            ['views' => DB::raw('views + 1')],
        );

        Prompt::query()->whereKey($prompt->id)->increment('views_count');

        return true;
    }

    /** Dense 30-day series of daily views for a prompt or across all. */
    public function viewsSeries(?int $promptId = null): array
    {
        $cacheKey = 'analytics:views:'.($promptId ?? 'all');

        return $this->cachedSeries($cacheKey, fn () => $this->buildDailySeries(
            PromptDailyStat::query()
                ->when($promptId !== null, fn ($q) => $q->where('prompt_id', $promptId))
                ->where('day', '>=', now()->subDays(29)->toDateString())
                ->groupBy('day')
                ->orderBy('day')
                ->selectRaw('day, sum(views) as total'),
            'total',
        ));
    }

    /** Dense 30-day series of paid-order totals (paisa) — or order counts. */
    public function salesSeries(?User $creator = null, bool $countOnly = false): array
    {
        return $this->cachedSeries("analytics:sales:".($creator?->id ?? 'all').':'.($countOnly ? 'count' : 'paisa'), function () use ($creator, $countOnly) {
            $query = Order::query()
                ->where('status', Order::STATUS_PAID)
                ->where('paid_at', '>=', now()->subDays(29)->startOfDay());

            if ($creator !== null) {
                // Creator-scoped: orders containing a line for their prompts.
                $query->whereHas('items', fn ($q) => $q
                    ->whereHas('product.prompt', fn ($p) => $p->where('user_id', $creator->id))
                    ->orWhereHas('prompt', fn ($p) => $p->where('user_id', $creator->id)));
            }

            $aggregate = $countOnly ? 'count(*) as total' : 'sum(total_paisa) as total';

            return $this->buildDailySeries(
                $query->groupBy(DB::raw('date(paid_at)'))->orderBy(DB::raw('date(paid_at)'))->selectRaw($aggregate.', date(paid_at) as day'),
                'total',
                $countOnly,
            );
        });
    }

    /** Rating average series (30 days) for a creator's prompts. */
    public function ratingSeries(User $creator): array
    {
        $series = $this->cachedSeries("analytics:ratings:{$creator->id}", fn () => $this->buildDailySeries(
            DB::table('ratings')
                ->join('prompts', 'prompts.id', '=', 'ratings.prompt_id')
                ->where('prompts.user_id', $creator->id)
                ->where('ratings.created_at', '>=', now()->subDays(29)->startOfDay())
                ->groupBy(DB::raw('date(ratings.created_at)'))
                ->orderBy(DB::raw('date(ratings.created_at)'))
                ->selectRaw('round(avg(ratings.score) * 100) as total, date(ratings.created_at) as day'),
            'total',
        ));

        // buildDailySeries returns a plain array (dense, integer-cast); the
        // /100 scaling happens on the array here.
        return array_map(fn ($v) => $v / 100, $series);
    }

    /** Admin overview: new users per day (30d). */
    public function usersSeries(): array
    {
        return $this->cachedSeries('analytics:users:all', fn () => $this->buildDailySeries(
            User::query()
                ->where('created_at', '>=', now()->subDays(29)->startOfDay())
                ->groupBy(DB::raw('date(created_at)'))
                ->orderBy(DB::raw('date(created_at)'))
                ->selectRaw('count(*) as total, date(created_at) as day'),
            'total',
        ));
    }

    /** Admin overview: open reports per day (30d). */
    public function reportsSeries(): array
    {
        return $this->cachedSeries('analytics:reports:all', fn () => $this->buildDailySeries(
            PromptReport::query()
                ->where('created_at', '>=', now()->subDays(29)->startOfDay())
                ->groupBy(DB::raw('date(created_at)'))
                ->orderBy(DB::raw('date(created_at)'))
                ->selectRaw('count(*) as total, date(created_at) as day'),
            'total',
        ));
    }

    // ------------------------------------------------------------

    /** 10-minute file cache (cPanel parity — file driver is the default). */
    private function cachedSeries(string $key, callable $builder): array
    {
        return cache()->remember($key, now()->addMinutes(10), $builder);
    }

    /**
     * Build a DENSE 30-point array (dates → values, missing days = 0).
     * Empty data is a first-class state: the result is 30 zeros, never
     * sparse/null (the K-series lesson applied to analytics).
     */
    private function buildDailySeries($query, string $valueColumn, bool $integer = true): array
    {
        $rows = $query->get()->keyBy(fn ($row) => substr((string) ($row->day ?? ''), 0, 10));

        $series = [];
        foreach (range(29, 0) as $ago) {
            $day = now()->subDays($ago)->toDateString();
            $value = $rows[$day]?->{$valueColumn} ?? 0;
            $series[$day] = $integer ? (int) $value : (float) $value;
        }

        return $series;
    }
}
