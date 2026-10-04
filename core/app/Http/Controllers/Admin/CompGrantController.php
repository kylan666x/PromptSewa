<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Prompt;
use App\Models\User;
use App\Services\CompGrantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A5: complimentary license grants. Admin picks a user and a prompt,
 * writes a reason, and the account owns the prompt for free. Every comp
 * lands in the license ledger marked tier `comp` with issuer + reason.
 *
 * R5 (v1.7.7 Bug Hunt Raid) — BH-001/BH-002 root cause and redesign.
 * The picker used to be built with `Prompt::query()->published()`: the
 * `published()` scope is `where('status', STATUS_PUBLISHED)`, so every
 * draft/pending/rejected prompt was invisible (the founder's "cannot see
 * all of my prompts"), and `store()` re-applied it so a draft 404'd even
 * when posted by id. The page now server-renders the FULL prompt list
 * into a searchable Alpine combobox (277 rows render once; filter is
 * client-side), and both create() and store() treat every prompt as
 * grantable. Validation, admin gating, idempotency and audit are
 * unchanged.
 */
class CompGrantController extends Controller
{
    public function __construct(
        private readonly CompGrantService $comps,
    ) {}

    public function create(Request $request)
    {
        // R1 (v1.7.7 raid): the read door must carry the same admin gate as
        // the write door (§6.36 — badge/frame indexes served 200 to
        // moderators for exactly this reason). The role matrix locks it.
        abort_unless($request->user()?->isAdmin(), 403, 'Only admins can issue comp grants.');

        $query = trim((string) $request->query('q', ''));

        return view('admin.comp-grants', [
            'users' => User::query()
                ->when($query !== '', fn ($b) => $b->where(fn ($w) => $w
                    ->where('email', 'like', "%{$query}%")
                    ->orWhere('name', 'like', "%{$query}%")
                    ->orWhere('username', 'like', "%{$query}%")))
                ->orderBy('name')
                ->limit(300)
                ->get(['id', 'name', 'username', 'email']),
            // Every prompt, every status — the founder ruling. The list is
            // server-rendered once; the combobox filters client-side.
            'prompts' => Prompt::query()
                ->orderBy('title')
                ->get(['id', 'title', 'status', 'price_cents']),
            'search' => $query,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only admins can issue comp grants.');

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'prompt_id' => ['required', 'integer', 'exists:prompts,id'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);
        // R5: any prompt is grantable — drafts included. The old
        // `published()` scope here was half of BH-001/BH-002.
        $prompt = Prompt::query()->findOrFail($validated['prompt_id']);

        // F6 (v1.7.8): the grant row and its bell notification commit
        // together; an idempotent replay notifies nobody.
        $grant = DB::transaction(function () use ($user, $prompt, $request, $validated) {
            $grant = $this->comps->grant($user, $prompt, $request->user(), $validated['reason']);

            if ($grant !== null) {
                Notification::emit(
                    $user,
                    Notification::TYPE_COMP_GRANT,
                    "A complimentary copy of \"{$prompt->title}\" was added to your library.",
                    $prompt,
                );
            }

            return $grant;
        });

        if ($grant === null) {
            return back()->withErrors([
                'user_id' => "{$user->name} already holds an active license for \"{$prompt->title}\".",
            ]);
        }

        return back()->with('success', "Comp grant issued — {$user->name} now owns \"{$prompt->title}\" (ledger tier: comp).");
    }
}
