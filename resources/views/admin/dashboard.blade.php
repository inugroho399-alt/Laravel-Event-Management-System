<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                    {{ __('Administrator Dashboard') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    {{ __('Platform-wide oversight, user roles, system metrics, and global activity.') }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.users.index') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold text-xs uppercase tracking-widest transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    {{ __('Manage Users') }}
                </a>
                <a href="{{ route('admin.events.index') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    {{ __('All Events') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            {{-- ── PLATFORM KPI CARDS ───────────────────────────────────────── --}}
            <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Users</div>
                    <div class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($totalUsers) }}</div>
                    <div class="text-xs text-gray-400 mt-1 flex items-center gap-1.5 flex-wrap">
                        <span class="text-indigo-600 font-medium">{{ $organizerUsers }} org</span> &bull;
                        <span class="text-sky-600 font-medium">{{ $participantUsers }} part</span> &bull;
                        <span class="text-purple-600 font-medium">{{ $adminUsers }} adm</span>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Events</div>
                    <div class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($totalEvents) }}</div>
                    <div class="text-xs text-gray-400 mt-1 flex items-center gap-1.5 flex-wrap">
                        <span class="text-emerald-600 font-medium">{{ $publishedEvents }} pub</span> &bull;
                        <span class="text-amber-600 font-medium">{{ $ongoingEvents }} ong</span> &bull;
                        <span class="text-blue-600 font-medium">{{ $completedEvents }} done</span>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Registrations</div>
                    <div class="text-3xl font-bold text-indigo-600 mt-1">{{ number_format($totalRegistrations) }}</div>
                    <div class="text-xs text-indigo-500/80 mt-1">{{ $capacityUtilization }}% of platform capacity</div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Check-ins</div>
                    <div class="text-3xl font-bold text-emerald-600 mt-1">{{ number_format($totalCheckIns) }}</div>
                    <div class="text-xs text-emerald-600 mt-1">{{ $overallAttendanceRate }}% turnout rate</div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Capacity</div>
                    <div class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($totalCapacity) }}</div>
                    <div class="text-xs text-gray-400 mt-1">across all ticket tiers</div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm col-span-2 lg:col-span-1">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Platform Revenue</div>
                    <div class="text-3xl font-bold text-amber-600 mt-1">${{ number_format($totalRevenue, 2) }}</div>
                    <div class="text-xs text-amber-500/80 mt-1">all organizers</div>
                </div>
            </div>

            {{-- ── ADMINISTRATIVE QUICK ACTIONS ────────────────────────────── --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">Administrative Oversight</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                    <a href="{{ route('admin.users.index') }}"
                       class="flex flex-col items-center gap-2 p-4 rounded-xl border border-gray-100 hover:border-indigo-200 hover:bg-indigo-50 transition group text-center">
                        <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 group-hover:bg-indigo-200 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <span class="text-xs font-semibold text-gray-700 group-hover:text-indigo-700">User Management</span>
                    </a>

                    <a href="{{ route('admin.events.index') }}"
                       class="flex flex-col items-center gap-2 p-4 rounded-xl border border-gray-100 hover:border-emerald-200 hover:bg-emerald-50 transition group text-center">
                        <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 group-hover:bg-emerald-200 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <span class="text-xs font-semibold text-gray-700 group-hover:text-emerald-700">All Events Oversight</span>
                    </a>

                    <a href="{{ route('admin.registrations.index') }}"
                       class="flex flex-col items-center gap-2 p-4 rounded-xl border border-gray-100 hover:border-sky-200 hover:bg-sky-50 transition group text-center">
                        <div class="w-10 h-10 rounded-full bg-sky-100 flex items-center justify-center text-sky-600 group-hover:bg-sky-200 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                            </svg>
                        </div>
                        <span class="text-xs font-semibold text-gray-700 group-hover:text-sky-700">Registrations</span>
                    </a>

                    <a href="{{ route('organizer.reports.index') }}"
                       class="flex flex-col items-center gap-2 p-4 rounded-xl border border-gray-100 hover:border-purple-200 hover:bg-purple-50 transition group text-center">
                        <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 group-hover:bg-purple-200 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <span class="text-xs font-semibold text-gray-700 group-hover:text-purple-700">System Reports</span>
                    </a>

                    <a href="{{ route('organizer.events.create') }}"
                       class="flex flex-col items-center gap-2 p-4 rounded-xl border border-gray-100 hover:border-blue-200 hover:bg-blue-50 transition group text-center">
                        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 group-hover:bg-blue-200 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </div>
                        <span class="text-xs font-semibold text-gray-700 group-hover:text-blue-700">Create New Event</span>
                    </a>
                </div>
            </div>

            {{-- ── RECENT PLATFORM ACTIVITY (2x2 GRID) ──────────────────────── --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- Recent Events --}}
                <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800">Recent Events</h3>
                        <a href="{{ route('admin.events.index') }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View all &rarr;</a>
                    </div>
                    @if ($recentEvents->count() > 0)
                        <ul class="divide-y divide-gray-50">
                            @foreach ($recentEvents as $ev)
                                <li class="px-6 py-3 flex items-start gap-3">
                                    <div class="mt-0.5 w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 shrink-0 text-xs font-bold">
                                        {{ strtoupper(substr($ev->title, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium text-sm text-gray-900 truncate">{{ $ev->title }}</div>
                                        <div class="text-xs text-gray-500">by {{ $ev->organizer?->name }} &bull; {{ $ev->created_at?->diffForHumans() }}</div>
                                    </div>
                                    @php
                                        $statusClass = match ($ev->status) {
                                            \App\Enums\EventStatus::Published => 'bg-emerald-100 text-emerald-800',
                                            \App\Enums\EventStatus::Ongoing   => 'bg-amber-100 text-amber-800',
                                            \App\Enums\EventStatus::Completed => 'bg-blue-100 text-blue-800',
                                            default => 'bg-gray-100 text-gray-800',
                                        };
                                    @endphp
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $statusClass }}">
                                        {{ $ev->status->label() }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="p-8 text-center text-sm text-gray-400">No events created yet.</div>
                    @endif
                </div>

                {{-- Recent Users --}}
                <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800">Recent Users</h3>
                        <a href="{{ route('admin.users.index') }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View all &rarr;</a>
                    </div>
                    @if ($recentUsers->count() > 0)
                        <ul class="divide-y divide-gray-50">
                            @foreach ($recentUsers as $usr)
                                <li class="px-6 py-3 flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-600 shrink-0 text-xs font-bold">
                                        {{ strtoupper(substr($usr->name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium text-sm text-gray-900 truncate">{{ $usr->name }}</div>
                                        <div class="text-xs text-gray-400 truncate">{{ $usr->email }}</div>
                                    </div>
                                    @php
                                        $roleClass = match ($usr->role) {
                                            \App\Enums\UserRole::Admin       => 'bg-purple-100 text-purple-800',
                                            \App\Enums\UserRole::Organizer   => 'bg-indigo-100 text-indigo-800',
                                            \App\Enums\UserRole::Participant => 'bg-gray-100 text-gray-800',
                                        };
                                    @endphp
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $roleClass }}">
                                        {{ $usr->role->label() }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="p-8 text-center text-sm text-gray-400">No users found.</div>
                    @endif
                </div>

                {{-- Recent Registrations --}}
                <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="font-semibold text-gray-800">Recent Registrations</h3>
                    </div>
                    @if ($recentRegistrations->count() > 0)
                        <ul class="divide-y divide-gray-50">
                            @foreach ($recentRegistrations as $reg)
                                <li class="px-6 py-3 flex items-start gap-3">
                                    <div class="mt-0.5 w-8 h-8 rounded-full bg-sky-100 flex items-center justify-center text-sky-600 shrink-0 text-xs font-bold">
                                        {{ strtoupper(substr($reg->user?->name ?? '?', 0, 1)) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium text-sm text-gray-900 truncate">{{ $reg->user?->name }}</div>
                                        <div class="text-xs text-gray-500 truncate">{{ $reg->event?->title }} &bull; {{ $reg->ticketType?->name }}</div>
                                    </div>
                                    <span class="text-xs font-semibold text-gray-600 shrink-0">
                                        {{ $reg->created_at?->diffForHumans() }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="p-8 text-center text-sm text-gray-400">No registrations yet.</div>
                    @endif
                </div>

                {{-- Recent Check-ins --}}
                <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="font-semibold text-gray-800">Recent Check-ins</h3>
                    </div>
                    @if ($recentCheckIns->count() > 0)
                        <ul class="divide-y divide-gray-50">
                            @foreach ($recentCheckIns as $ci)
                                <li class="px-6 py-3 flex items-start gap-3">
                                    <div class="mt-0.5 w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium text-sm text-gray-900 truncate">{{ $ci->registration?->user?->name }}</div>
                                        <div class="text-xs text-gray-500 truncate">{{ $ci->registration?->event?->title }}</div>
                                        <div class="text-xs text-gray-400">verified by {{ $ci->checker?->name }} &bull; {{ $ci->checked_in_at?->diffForHumans() }}</div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="p-8 text-center text-sm text-gray-400">No check-ins recorded yet.</div>
                    @endif
                </div>

            </div>

        </div>
    </div>
</x-app-layout>

