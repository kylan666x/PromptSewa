<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prompt;
use App\Models\User;
use App\Services\CompGrantService;
use Illuminate\Http\Request;

/**
 * A5: complimentary license grants. Admin picks a user and a prompt,
 * writes a reason, and the account owns the prompt for free. Every comp
 * lands in the license ledger marked tier `comp` with issuer + reason.
 */
class CompGrantController extends Controller
{
    public function __construct(
        private readonly CompGrantService $comps,
    ) {}

    public function create(Request $request)
    {
        $query = trim((string) $request->query('q', ''));

        return view('admin.comp-grants', [
            'users' => User::query()
                ->when($query !== '', fn ($b) => $b->where(fn ($w) => $w
                    ->where('email', 'like', "%{$query}%")
                    ->orWhere('name', 'like', "%{$query}%")
                    ->orWhere('username', 'like', "%{$query}%")))
                ->orderBy('name')
                ->limit(25)
                ->get(['id', 'name', 'username', 'email']),
            'prompts' => Prompt::query()
                ->published()
                ->orderBy('title')
                ->get(['id', 'title', 'price_cents']),
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
        $prompt = Prompt::query()->published()->findOrFail($validated['prompt_id']);

        $grant = $this->comps->grant($user, $prompt, $request->user(), $validated['reason']);

        if ($grant === null) {
            return back()->withErrors([
                'user_id' => "{$user->name} already holds an active license for \"{$prompt->title}\".",
            ]);
        }

        return back()->with('success', "Comp grant issued — {$user->name} now owns \"{$prompt->title}\" (ledger tier: comp).");
    }
}
