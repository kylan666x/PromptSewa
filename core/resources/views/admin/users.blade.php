<x-admin-layout title="Users">
    @php
        /** @var \Illuminate\Pagination\LengthAwarePaginator $users */
        use App\Models\User;
        $roles = [User::ROLE_MEMBER, User::ROLE_CREATOR, User::ROLE_MODERATOR, User::ROLE_ADMIN];
    @endphp

    <form method="GET" class="flex flex-wrap items-center gap-2">
        <input type="text" name="q" value="{{ $search }}" placeholder="Search name or email…"
               class="w-64 rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2 text-sm text-ink placeholder-creak outline-none focus:border-saffron-deep">
        <select name="role" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink/90 outline-none focus:border-saffron-deep">
            <option value="">All roles</option>
            @foreach ($roles as $roleOption)
                <option value="{{ $roleOption }}" @selected($currentRole === $roleOption)>{{ ucfirst($roleOption) }}</option>
            @endforeach
        </select>
        <button class="rounded-xl bg-saffron px-4 py-2 text-sm font-semibold text-ink transition hover:bg-saffron-deep">Filter</button>
    </form>

    <div class="mt-6 overflow-hidden rounded-2xl border border-ink/10">
        <table class="min-w-full divide-y divide-ink/10 text-sm">
            <thead class="bg-paper-deep text-left text-xs uppercase tracking-wider text-ink0">
                <tr>
                    <th class="px-5 py-3 font-semibold">User</th>
                    <th class="hidden px-5 py-3 font-semibold sm:table-cell">Prompts</th>
                    <th class="hidden px-5 py-3 font-semibold md:table-cell">Joined</th>
                    <th class="px-5 py-3 font-semibold">Role</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink/10 bg-white">
                @forelse ($users as $user)
                    <tr class="transition hover:bg-paper-deep">
                        <td class="px-5 py-3.5">
                            <p class="font-medium text-ink">{{ $user->name }}</p>
                            <p class="text-xs text-ink0">{{ $user->email }}</p>
                        </td>
                        <td class="hidden px-5 py-3.5 text-ink/60 sm:table-cell">{{ $user->prompts_count }}</td>
                        <td class="hidden px-5 py-3.5 text-ink/60 md:table-cell">{{ $user->created_at->format('M j, Y') }}</td>
                        <td class="px-5 py-3.5">
                            @if (auth()->user()->isAdmin() && $user->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.users.role', $user) }}" class="flex items-center gap-1.5">
                                    @csrf
                                    @method('PATCH')
                                    <select name="role" class="rounded-lg border border-ink/10 bg-paper-deep px-2 py-1 text-xs text-ink/90 outline-none">
                                        @foreach ($roles as $roleOption)
                                            <option value="{{ $roleOption }}" @selected($user->role === $roleOption)>{{ ucfirst($roleOption) }}</option>
                                        @endforeach
                                    </select>
                                    <button class="rounded-lg border border-ink/10 px-2 py-1 text-xs text-ink/80 transition hover:border-saffron-deep hover:text-saffron-deep">Set</button>
                                </form>
                            @else
                                <span class="rounded-md bg-paper-deep px-2 py-0.5 text-xs font-medium text-ink/80">{{ ucfirst($user->role) }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-10 text-center text-ink0">No users match.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $users->links() }}</div>
</x-admin-layout>
