<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\Frame;
use App\Models\MembershipPlan;
use App\Models\OrderItem;
use App\Models\SikkaPack;
use App\Models\SikkaTransaction;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\SikkaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * S6 (v1.8.0) — Admin → Sikka desk.
 *
 * One admin-only door (moderators 403, §6.36 pill shipped in the same
 * commit) for the whole economy:
 *
 *   - rates + bounds: the buy rate (50–500 paisa/credit) and the
 *     cash-out rate (10 … buy, so the spread can never invert), the
 *     engagement amounts + daily cap, and the cash-out minimum. (The
 *     Sikka kill-switch is RETIRED — Sikka is the permanent economy and
 *     the desk no longer exposes sikka_enabled.)
 *   - packs + plans CRUD (delete degrades to deactivate once a purchase
 *     references the row — restrictOnDelete keeps the audit trail);
 *   - the insert-only ledger browser (type + eligibility chips);
 *   - admin grants: one admin_grant row, spend-only by default, with a
 *     per-grant eligibility FLIP to cash-out-able — the flip requires the
 *     mandatory reason and is audited in the row's meta AND the admin log.
 *     (Eligibility is decided AT WRITE TIME: the ledger is insert-only and
 *     never rewritten — there is no UPDATE path, by contract.)
 */
class SikkaAdminController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly SikkaService $sikka,
    ) {}

    public function index(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only admins can open the Sikka desk.');

        $ledger = SikkaTransaction::query()
            ->with('user')
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', (int) $request->query('user')))
            ->when($request->filled('type') && in_array($request->query('type'), SikkaTransaction::TYPES, true),
                fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->query('eligibility') === 'cashout', fn ($q) => $q->where('cashout_eligible', true))
            ->when($request->query('eligibility') === 'spend', fn ($q) => $q->where('cashout_eligible', false))
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.sikka', [
            'buyRate' => (int) ($this->settings->get('sikka_buy_paisa_per_token', '100') ?? '100'),
            'cashoutRate' => $this->sikka->cashoutRatePaisaPerToken(),
            'engage' => [
                'daily' => (int) ($this->settings->get('engage_daily_sikka', '1') ?? '1'),
                'publish' => (int) ($this->settings->get('engage_publish_sikka', '2') ?? '2'),
                'rating' => (int) ($this->settings->get('engage_rating_sikka', '1') ?? '1'),
                'cap' => (int) ($this->settings->get('engage_daily_cap_sikka', '5') ?? '5'),
            ],
            'cashoutMin' => (int) ($this->settings->get('sikka_cashout_min', '500') ?? '500'),
            'packs' => SikkaPack::query()->orderBy('price_paisa')->get(),
            'plans' => MembershipPlan::query()->orderBy('price_paisa')->get(),
            'badges' => Badge::query()->orderBy('name')->get(['id', 'name']),
            'frames' => Frame::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->limit(500)->get(['id', 'name', 'email']),
            'ledger' => $ledger,
            'filters' => $request->only(['user', 'type', 'eligibility']),
            'stats' => [
                // Circulation + spend are derived from the insert-only ledger
                // (SUM), never stored. (int) casts on every boundary (K2).
                'circulation' => (int) SikkaTransaction::query()->sum('amount_sikka'),
                'cashoutable' => (int) SikkaTransaction::query()->where('cashout_eligible', true)->sum('amount_sikka'),
                'spent' => (int) abs((int) SikkaTransaction::query()->where('type', SikkaTransaction::TYPE_SPEND)->sum('amount_sikka')),
            ],
        ]);
    }

    /** Rates, bounds, engagement amounts. The kill-switch is retired. */
    public function updateSettings(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $buy = (int) $request->input('sikka_buy_paisa_per_token');

        $validated = $request->validate([
            // Bounds are the contract (S1/S6): buy 50–500, cash-out 10…buy.
            'sikka_buy_paisa_per_token' => ['required', 'integer', 'min:50', 'max:500'],
            'sikka_cashout_paisa_per_token' => ['required', 'integer', 'min:10', 'max:'.max(10, $buy)],
            'engage_daily_sikka' => ['required', 'integer', 'min:0', 'max:50'],
            'engage_publish_sikka' => ['required', 'integer', 'min:0', 'max:50'],
            'engage_rating_sikka' => ['required', 'integer', 'min:0', 'max:50'],
            'engage_daily_cap_sikka' => ['required', 'integer', 'min:0', 'max:500'],
            'sikka_cashout_min' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        foreach (['sikka_buy_paisa_per_token', 'sikka_cashout_paisa_per_token', 'engage_daily_sikka',
            'engage_publish_sikka', 'engage_rating_sikka', 'engage_daily_cap_sikka', 'sikka_cashout_min'] as $key) {
            $this->settings->set($key, (string) $validated[$key]);
        }

        return back()->with('success', 'Sikka settings saved.');
    }

    // -------------------------------------------------------------
    // Packs CRUD
    // -------------------------------------------------------------

    public function storePack(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'slug' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[a-z0-9-]+$/', 'unique:sikka_packs,slug'],
            'sikka_amount' => ['required', 'integer', 'min:1', 'max:1000000'],
            'bonus_sikka' => ['required', 'integer', 'min:0', 'max:1000000'],
            'price_paisa' => ['required', 'integer', 'min:0', 'max:100000000'],
        ]);

        $pack = SikkaPack::query()->create([...$validated, 'active' => $request->boolean('active', true)]);

        return back()->with('success', "Sikka pack \"{$pack->name}\" created.");
    }

    public function updatePack(Request $request, SikkaPack $sikkaPack)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'slug' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('sikka_packs', 'slug')->ignore($sikkaPack->id)],
            'sikka_amount' => ['required', 'integer', 'min:1', 'max:1000000'],
            'bonus_sikka' => ['required', 'integer', 'min:0', 'max:1000000'],
            'price_paisa' => ['required', 'integer', 'min:0', 'max:100000000'],
        ]);

        $sikkaPack->update([...$validated, 'active' => $request->boolean('active')]);

        return back()->with('success', "Sikka pack \"{$sikkaPack->name}\" updated.");
    }

    public function destroyPack(Request $request, SikkaPack $sikkaPack)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        // A purchase references the row (restrictOnDelete) — packs are
        // merchandising history once sold, so deletion degrades honestly.
        $referenced = $sikkaPack->orderItems()->exists() ?? false;

        if ($referenced) {
            $sikkaPack->update(['active' => false]);

            return back()->with('success', "\"{$sikkaPack->name}\" has purchases — deactivated instead of deleted.");
        }

        $sikkaPack->delete();

        return back()->with('success', "Sikka pack \"{$sikkaPack->name}\" deleted.");
    }

    // -------------------------------------------------------------
    // Plans CRUD (perks picker)
    // -------------------------------------------------------------

    public function storePlan(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $this->validatePlan($request);

        $plan = MembershipPlan::query()->create($this->planAttributes($request, $validated));

        return back()->with('success', "Membership plan \"{$plan->name}\" created.");
    }

    public function updatePlan(Request $request, MembershipPlan $membershipPlan)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $this->validatePlan($request, $membershipPlan);

        $membershipPlan->update($this->planAttributes($request, $validated));

        return back()->with('success', "Membership plan \"{$membershipPlan->name}\" updated.");
    }

    public function destroyPlan(Request $request, MembershipPlan $membershipPlan)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        // Memberships (and order lines) reference plans — deactivate instead
        // of deleting history (restrictOnDelete by migration).
        $referenced = $membershipPlan->memberships()->exists()
            || OrderItem::query()->where('membership_plan_id', $membershipPlan->id)->exists();

        if ($referenced) {
            $membershipPlan->update(['active' => false]);

            return back()->with('success', "\"{$membershipPlan->name}\" has members — deactivated instead of deleted.");
        }

        $membershipPlan->delete();

        return back()->with('success', "Membership plan \"{$membershipPlan->name}\" deleted.");
    }

    // -------------------------------------------------------------
    // Admin grants (the per-grant eligibility flip)
    // -------------------------------------------------------------

    /**
     * Issue one admin_grant row. Spend-only by default; checking the flip
     * makes THIS grant cash-out-able. The mandatory reason is recorded in
     * the row's meta AND the admin log (Log::info) — and because the
     * ledger is insert-only, write time is the only moment eligibility
     * can ever be decided (no UPDATE path exists).
     */
    public function grant(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'amount_sikka' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'cashout_eligible' => ['nullable', 'boolean'],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);
        $eligible = $request->boolean('cashout_eligible');

        $row = SikkaTransaction::query()->create([
            'user_id' => $user->id,
            'type' => SikkaTransaction::TYPE_ADMIN_GRANT,
            'amount_sikka' => (int) $validated['amount_sikka'],
            'cashout_eligible' => $eligible,
            'idempotency_key' => 'admingrant:'.Str::uuid(),
            'meta' => [
                'reason' => trim($validated['reason']),
                'granted_by' => $request->user()->id,
                'eligibility_flip' => $eligible,
            ],
            'created_at' => now(),
        ]);

        Log::info('sikka.admin_grant', [
            'admin_id' => $request->user()->id,
            'user_id' => $user->id,
            'amount_sikka' => $row->amount_sikka,
            'cashout_eligible' => $eligible,
            'reason' => trim($validated['reason']),
        ]);

        return back()->with('success', 'Granted '.$row->amount_sikka.' Sikka credits to '.$user->name
            .($eligible ? ' (cash-out eligible — flip audited).' : ' (spend only).'));
    }

    // -------------------------------------------------------------

    /** @return array<string, mixed> */
    private function validatePlan(Request $request, ?MembershipPlan $existing = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'slug' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('membership_plans', 'slug')->ignore($existing?->id)],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            // S1b (v1.9.0): membership plans price in Sikka credits; the
            // NPR mirror (price_paisa) derives on save.
            'price_sikka' => ['required', 'integer', 'min:0', 'max:100000'],
            'stipend_sikka' => ['required', 'integer', 'min:0', 'max:100000'],
            'badge_id' => ['nullable', 'integer', 'exists:badges,id'],
            'frame_id' => ['nullable', 'integer', 'exists:frames,id'],
            'unlimited_unlock' => ['nullable', 'boolean'],
            'grant_verified' => ['nullable', 'boolean'],
        ]);
    }

    /** @return array<string, mixed> */
    private function planAttributes(Request $request, array $validated): array
    {
        return [
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'duration_days' => $validated['duration_days'],
            'price_sikka' => $validated['price_sikka'],
            'stipend_sikka' => $validated['stipend_sikka'],
            'perks' => [
                MembershipPlan::PERK_UNLIMITED_UNLOCK => $request->boolean('unlimited_unlock'),
                MembershipPlan::PERK_BADGE_ID => $validated['badge_id'] ?? null,
                MembershipPlan::PERK_FRAME_ID => $validated['frame_id'] ?? null,
                MembershipPlan::PERK_GRANT_VERIFIED => $request->boolean('grant_verified'),
            ],
            'active' => $request->boolean('active'),
        ];
    }
}
