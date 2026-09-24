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

        $users = User::query()
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
}
