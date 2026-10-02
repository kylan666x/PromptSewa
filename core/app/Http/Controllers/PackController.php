<?php

namespace App\Http\Controllers;

use App\Models\Pack;
use App\Models\Rating;

/**
 * Public pack browsing: curated bundles of prompts at one price.
 */
class PackController extends Controller
{
    public function index()
    {
        $packs = Pack::query()
            ->active()
            ->withCount('publishedPrompts')
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->filter(fn (Pack $pack) => $pack->published_prompts_count > 0)
            ->values();

        return view('packs.index', ['packs' => $packs]);
    }

    public function show(Pack $pack)
    {
        abort_unless($pack->is_active, 404);

        // Fix (v1.5.0): latest() emitted a bare `order by created_at` inside
        // the belongsToMany window function — ambiguous between prompts and
        // the pivot, so any pack with contents 500'd. Qualify the column.
        $pack->load(['publishedPrompts' => fn ($query) => $query->with(['category', 'creator', 'latestVersion'])
            ->orderByDesc('prompts.created_at')
            ->take(24)]);

        // G2 (v1.7.4) — landing v2 stat chips. Every number here is computed
        // from the database; the hero shows "No ratings yet" rather than a
        // fabricated average, and the savings line is integer paisa (never a
        // float sum). Voice rule: the inspiration contributes LAYOUT only —
        // no invented counts, testimonials or "as seen in" claims.
        $promptIds = $pack->publishedPrompts->pluck('id');

        $ratingRow = $promptIds->isEmpty()
            ? null
            : Rating::query()
                ->whereIn('prompt_id', $promptIds)
                ->selectRaw('COUNT(*) as aggregate_count, AVG(score) as aggregate_avg')
                ->first();

        $ratingCount = (int) ($ratingRow->aggregate_count ?? 0);
        $ratingAvg = $ratingCount > 0
            ? round((float) $ratingRow->aggregate_avg, 1)
            : null;

        // Real money maths in paisa: what the contents cost separately minus
        // the pack price. Negative (pack dearer than contents) is NOT shown
        // as a saving — the page would be lying.
        $individualSumPaisa = (int) $pack->publishedPrompts->sum('price_cents');
        $savingsPaisa = max(0, $individualSumPaisa - (int) $pack->price_paisa);

        $related = Pack::query()
            ->active()
            ->whereKeyNot($pack->id)
            ->withCount('publishedPrompts')
            ->orderByDesc('position')
            ->orderBy('name')
            ->get()
            ->filter(fn (Pack $other) => $other->published_prompts_count > 0)
            ->take(3)
            ->values();

        return view('packs.show', [
            'pack' => $pack,
            'ratingCount' => $ratingCount,
            'ratingAvg' => $ratingAvg,
            'individualSumPaisa' => $individualSumPaisa,
            'savingsPaisa' => $savingsPaisa,
            'relatedPacks' => $related,
        ]);
    }
}
