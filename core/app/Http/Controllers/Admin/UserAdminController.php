<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Admin user management: search, inspect, change roles.
 *
 * Role changes are admin-only (moderators cannot escalate anyone) and a
 * user can never demote themselves — that keeps at least one working
 * admin account alive.
 */
class UserAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = trim((string) $request->query('q', ''));
        $role = (string) $request->query('role', '');

        // H1 (v1.5.2): the admin users column is "All prompts" — the TOTAL
        // across every status/visibility, deliberately unlike the public
        // profile stat (published-only). Locked by CreatorProfileCountTest.
        $users = User::query()
            ->with('activeFrame') // W1: the users table renders the frame overlay.
            ->withCount('prompts')
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%"));
            })
            ->when($role !== '' && in_array($role, [User::ROLE_MEMBER, User::ROLE_CREATOR, User::ROLE_MODERATOR, User::ROLE_ADMIN], true),
                fn ($builder) => $builder->where('role', $role))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.users', [
            'users' => $users,
            'search' => $query,
            'currentRole' => $role,
        ]);
    }

    public function updateRole(Request $request, User $user)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only admins can change roles.');

        if ($user->id === $request->user()?->id) {
            return back()->withErrors(['role' => 'You cannot change your own role.']);
        }

        $validated = $request->validate([
            'role' => ['required', 'in:'.implode(',', [User::ROLE_MEMBER, User::ROLE_CREATOR, User::ROLE_MODERATOR, User::ROLE_ADMIN])],
        ]);

        $user->fill(['role' => $validated['role']])->save();

        return back()->with('success', "{$user->name} is now a {$validated['role']}.");
    }

    /** Toggle the verified badge (admin only). */
    public function toggleVerified(Request $request, User $user)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only admins can issue verified badges.');

        $user->fill(['is_verified' => ! $user->is_verified])->save();

        $state = $user->is_verified ? 'verified ✓' : 'unverified';

        return back()->with('success', "{$user->name} is now {$state}.");
    }

    /** Ban / unban an account (admin only, A4). */
    public function toggleBanned(Request $request, User $user)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only admins can ban accounts.');

        // Never let an admin ban themselves — keeps at least one working
        // admin alive (same guard as the self-demotion rule).
        if ($user->id === $request->user()?->id) {
            return back()->withErrors(['banned_at' => 'You cannot ban your own account.']);
        }

        $user->fill(['banned_at' => $user->isBanned() ? null : now()])->save();

        $state = $user->isBanned() ? 'banned' : 'unbanned';

        return back()->with('success', "{$user->name} is now {$state}.");
    }
}
