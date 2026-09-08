<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <a href="{{ route('admin.dashboard') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition">
                        &larr; {{ __('Admin Dashboard') }}
                    </a>
                    <span class="text-gray-300">/</span>
                    <span class="text-xs text-gray-500 font-medium">Users</span>
                </div>
                <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                    {{ __('User Management') }}
                </h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ __('View all accounts, filter by role, update permissions, and manage user access.') }}
                </p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center gap-3">
                    <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm space-y-1">
                    @foreach ($errors->all() as $error)
                        <div>&bull; {{ $error }}</div>
                    @endforeach
                </div>
            @endif

            {{-- ── FILTER & SEARCH TOOLBAR ────────────────────────────────── --}}
            <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-col sm:flex-row items-center gap-4 justify-between">
                    <div class="flex-1 w-full flex flex-col sm:flex-row items-center gap-3">
                        <div class="w-full sm:w-80">
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Search by name or email..."
                                   class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <div class="w-full sm:w-48">
                            <select name="role" class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Roles</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->value }}" @selected(request('role') === $role->value)>
                                        {{ $role->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg uppercase tracking-wider transition shadow-sm">
                            Filter
                        </button>

                        @if (request()->hasAny(['search', 'role']))
                            <a href="{{ route('admin.users.index') }}" class="text-xs text-gray-500 hover:text-gray-700 underline">
                                Clear
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- ── USERS TABLE ─────────────────────────────────────────────── --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100">
                @if ($users->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-3 text-left font-semibold">User</th>
                                    <th class="px-6 py-3 text-left font-semibold">Role</th>
                                    <th class="px-6 py-3 text-center font-semibold">Events Organized</th>
                                    <th class="px-6 py-3 text-center font-semibold">Registrations</th>
                                    <th class="px-6 py-3 text-left font-semibold">Joined Date</th>
                                    <th class="px-6 py-3 text-right font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 bg-white">
                                @foreach ($users as $u)
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="font-semibold text-gray-900">{{ $u->name }}</div>
                                            <div class="text-xs text-gray-400">{{ $u->email }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <x-status-badge :status="$u->role" />
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-gray-700 font-medium">
                                            {{ number_format($u->events_count) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-gray-700 font-medium">
                                            {{ number_format($u->registrations_count) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-500 text-xs">
                                            {{ $u->created_at?->format('M d, Y') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-xs space-x-3">
                                            <a href="{{ route('admin.users.edit', $u) }}"
                                               class="text-indigo-600 hover:text-indigo-900 font-medium">Edit Role</a>

                                            @if ($u->id !== auth()->id())
                                                <form method="POST" action="{{ route('admin.users.destroy', $u) }}" class="inline"
                                                      onsubmit="return confirm('Are you sure you want to delete user \'{{ $u->name }}\'? This will permanently remove their records.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-rose-600 hover:text-rose-900 font-medium">Delete</button>
                                                </form>
                                            @else
                                                <span class="text-gray-400 cursor-not-allowed text-xs" title="You cannot delete your own account.">Current User</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($users->hasPages())
                        <div class="px-6 py-4 border-t border-gray-100">
                            {{ $users->links() }}
                        </div>
                    @endif
                @else
                    <div class="p-10 text-center text-gray-400 text-sm">
                        No users found matching your criteria.
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>

