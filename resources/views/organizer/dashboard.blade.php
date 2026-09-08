<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                    {{ __('Organizer Dashboard') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    {{ __('Overview of your events, registrations, and attendance.') }}
                </p>
            </div>
            <a href="{{ route('organizer.events.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ __('New Event') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col lg:flex-row gap-8">

                {{-- ── MODERN SIDEBAR NAVIGATION ────────────────────────────────── --}}
                <aside class="w-full lg:w-64 shrink-0" x-data="{ mobileNavOpen: false }">
                    <!-- Mobile Hamburger Accordion for Sidebar -->
                    <div class="lg:hidden mb-4 bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-indigo-600 animate-pulse"></span>
                                <span class="font-bold text-sm text-slate-900">Organizer Workspace</span>
                            </div>
                            <button @click="mobileNavOpen = !mobileNavOpen" type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 transition">
                                <span x-text="mobileNavOpen ? 'Hide Menu' : 'Workspace Menu'">Workspace Menu</span>
                                <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': mobileNavOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Sidebar Card -->
                    <div :class="{ 'block': mobileNavOpen, 'hidden': !mobileNavOpen }" class="hidden lg:block space-y-6 sticky top-28">
                        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 space-y-6">
                            <!-- Role & Workspace Identifier -->
                            <div class="pb-4 border-b border-slate-100">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Workspace</span>
                                    <x-status-badge status="organizer" size="sm" />
                                </div>
                                <h3 class="font-bold text-slate-900 text-base truncate">{{ Auth::user()->name }}</h3>
                                <p class="text-xs text-slate-500 truncate">{{ Auth::user()->email }}</p>
                            </div>

                            <!-- Navigation Links -->
                            <nav class="space-y-1">
                                <a href="{{ route('organizer.dashboard') }}"
                                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold {{ request()->routeIs('organizer.dashboard') ? 'bg-indigo-50 text-indigo-700 shadow-xs' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }} transition">
                                    <svg class="w-5 h-5 {{ request()->routeIs('organizer.dashboard') ? 'text-indigo-600' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                                    </svg>
                                    <span>Dashboard</span>
                                </a>

                                <a href="{{ route('organizer.events.index') }}"
                                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold {{ request()->routeIs('organizer.events.*') ? 'bg-indigo-50 text-indigo-700 shadow-xs' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }} transition">
                                    <svg class="w-5 h-5 {{ request()->routeIs('organizer.events.*') ? 'text-indigo-600' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <span>Manage Events</span>
                                </a>

                                <a href="{{ route('organizer.reports.index') }}"
                                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold {{ request()->routeIs('organizer.reports.*') ? 'bg-indigo-50 text-indigo-700 shadow-xs' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }} transition">
                                    <svg class="w-5 h-5 {{ request()->routeIs('organizer.reports.*') ? 'text-indigo-600' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                    </svg>
                                    <span>Reports & Exports</span>
                                </a>

                                <a href="{{ route('events.index') }}"
                                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition">
                                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                    <span>Discover Public</span>
                                </a>
                            </nav>

                            <!-- Mini Summary Widget -->
                            <div class="pt-4 border-t border-slate-100 bg-slate-50/70 -mx-5 -mb-5 p-5 rounded-b-2xl">
                                <div class="text-xs font-semibold text-slate-500 mb-2 uppercase tracking-wider">Quick Snapshot</div>
                                <div class="grid grid-cols-2 gap-2 text-center">
                                    <div class="bg-white p-2.5 rounded-xl border border-slate-200/60">
                                        <div class="text-xs text-slate-400">Events</div>
                                        <div class="font-bold text-slate-900 text-sm mt-0.5">{{ number_format($totalEvents) }}</div>
                                    </div>
                                    <div class="bg-white p-2.5 rounded-xl border border-slate-200/60">
                                        <div class="text-xs text-slate-400">Attended</div>
                                        <div class="font-bold text-emerald-600 text-sm mt-0.5">{{ number_format($totalAttended) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Create Event Fast Action -->
                        <div class="bg-gradient-to-br from-indigo-600 to-purple-700 rounded-2xl p-5 text-white shadow-md space-y-3">
                            <h4 class="font-bold text-sm">Host a New Event</h4>
                            <p class="text-xs text-indigo-100 leading-relaxed">Launch ticket tiers, configure quotas, and monitor check-ins.</p>
                            <a href="{{ route('organizer.events.create') }}"
                               class="inline-flex items-center justify-center w-full py-2 px-3 bg-white text-indigo-700 rounded-xl font-bold text-xs hover:bg-indigo-50 transition shadow-xs">
                                + Create New Event
                            </a>
                        </div>
                    </div>
                </aside>

                {{-- ── MAIN CANVAS CONTENT ─────────────────────────────────────── --}}
                <div class="flex-1 min-w-0 space-y-8">

                    {{-- ── KPI CARDS ───────────────────────────────────────────────── --}}
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7 gap-3 sm:gap-4">
                        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md transition">
                            <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Events</div>
                            <div class="text-2xl sm:text-3xl font-extrabold text-slate-900 mt-1">{{ number_format($totalEvents) }}</div>
                        </div>
                        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md transition">
                            <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Published</div>
                            <div class="text-2xl sm:text-3xl font-extrabold text-emerald-600 mt-1">{{ number_format($publishedEvents) }}</div>
                        </div>
                        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md transition">
                            <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Ticket Types</div>
                            <div class="text-2xl sm:text-3xl font-extrabold text-violet-600 mt-1">{{ number_format($totalTicketTypes) }}</div>
                        </div>
                        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md transition">
                            <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Registrations</div>
                            <div class="text-2xl sm:text-3xl font-extrabold text-indigo-600 mt-1">{{ number_format($totalRegistrations) }}</div>
                        </div>
                        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md transition">
                            <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Participants</div>
                            <div class="text-2xl sm:text-3xl font-extrabold text-sky-600 mt-1">{{ number_format($totalParticipants) }}</div>
                        </div>
                        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md transition">
                            <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Attended</div>
                            <div class="text-2xl sm:text-3xl font-extrabold text-blue-600 mt-1">{{ number_format($totalAttended) }}</div>
                        </div>
                        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md transition col-span-2 sm:col-span-1">
                            <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Revenue</div>
                            <div class="text-2xl sm:text-3xl font-extrabold text-amber-600 mt-1">${{ number_format($totalRevenue, 2) }}</div>
                        </div>
                    </div>

            {{-- ── UPCOMING EVENTS ─────────────────────────────────────────── --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800">Upcoming Events</h3>
                    <a href="{{ route('organizer.events.index') }}"
                       class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View all →</a>
                </div>

                @if ($upcomingEvents->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-3 text-left font-semibold">Event</th>
                                    <th class="px-6 py-3 text-left font-semibold">Date</th>
                                    <th class="px-6 py-3 text-left font-semibold">Status</th>
                                    <th class="px-6 py-3 text-left font-semibold">Registrations</th>
                                    <th class="px-6 py-3 text-left font-semibold">Check-in</th>
                                    <th class="px-6 py-3 text-right font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 bg-white">
                                @foreach ($upcomingEvents as $event)
                                    @php
                                        $checkInPct = $event->active_registrations_count > 0
                                            ? round(($event->attended_count / $event->active_registrations_count) * 100)
                                            : 0;
                                        $statusClass = match ($event->status) {
                                            \App\Enums\EventStatus::Published => 'bg-emerald-100 text-emerald-800',
                                            \App\Enums\EventStatus::Ongoing   => 'bg-amber-100 text-amber-800',
                                            default => 'bg-gray-100 text-gray-800',
                                        };
                                    @endphp
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="font-semibold text-gray-900">{{ $event->title }}</div>
                                            <div class="text-xs text-gray-400">{{ $event->location ?: 'Online' }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-600 text-xs">
                                            <div>{{ $event->start_date->format('M d, Y') }}</div>
                                            <div class="text-gray-400">{{ $event->start_date->format('h:i A') }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $statusClass }}">
                                                {{ $event->status->label() }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-700 font-medium">
                                            {{ number_format($event->active_registrations_count) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center gap-2">
                                                <div class="w-20 bg-gray-200 rounded-full h-1.5">
                                                    <div class="bg-emerald-500 h-1.5 rounded-full"
                                                         style="width: {{ $checkInPct }}%"></div>
                                                </div>
                                                <span class="text-xs text-gray-500">{{ $checkInPct }}%</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-xs space-x-3">
                                            <a href="{{ route('organizer.events.check-in.create', $event) }}"
                                               class="text-purple-600 hover:text-purple-800 font-medium">Check-in</a>
                                            <a href="{{ route('organizer.events.registrations.index', $event) }}"
                                               class="text-emerald-600 hover:text-emerald-800 font-medium">Attendees</a>
                                            <a href="{{ route('organizer.events.edit', $event) }}"
                                               class="text-gray-600 hover:text-gray-900 font-medium">Edit</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-10 text-center">
                        <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <p class="text-sm text-gray-500">No upcoming events scheduled.</p>
                        <a href="{{ route('organizer.events.create') }}"
                           class="inline-block mt-3 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                            Create your first event →
                        </a>
                    </div>
                @endif
            </div>

            {{-- ── RECENT EVENTS ─────────────────────────────────────────────── --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800">Recent Events</h3>
                    <a href="{{ route('organizer.events.index') }}"
                       class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View all →</a>
                </div>

                @if ($recentEvents->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-3 text-left font-semibold">Event</th>
                                    <th class="px-6 py-3 text-left font-semibold">Date</th>
                                    <th class="px-6 py-3 text-left font-semibold">Status</th>
                                    <th class="px-6 py-3 text-left font-semibold">Registrations</th>
                                    <th class="px-6 py-3 text-left font-semibold">Attended</th>
                                    <th class="px-6 py-3 text-right font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 bg-white">
                                @foreach ($recentEvents as $event)
                                    @php
                                        $statusClass = match ($event->status) {
                                            \App\Enums\EventStatus::Completed => 'bg-blue-100 text-blue-800',
                                            \App\Enums\EventStatus::Cancelled => 'bg-red-100 text-red-800',
                                            \App\Enums\EventStatus::Ongoing   => 'bg-amber-100 text-amber-800',
                                            \App\Enums\EventStatus::Published => 'bg-emerald-100 text-emerald-800',
                                            default => 'bg-gray-100 text-gray-800',
                                        };
                                    @endphp
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="font-semibold text-gray-900">{{ $event->title }}</div>
                                            <div class="text-xs text-gray-400">{{ $event->location ?: 'Online' }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-600 text-xs">
                                            <div>{{ $event->start_date->format('M d, Y') }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $statusClass }}">
                                                {{ $event->status->label() }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-700 font-medium">
                                            {{ number_format($event->active_registrations_count) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-700 font-medium">
                                            {{ number_format($event->attended_count) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-xs space-x-3">
                                            <a href="{{ route('organizer.events.registrations.index', $event) }}"
                                               class="text-emerald-600 hover:text-emerald-800 font-medium">Attendees</a>
                                            <a href="{{ route('organizer.events.edit', $event) }}"
                                               class="text-gray-600 hover:text-gray-900 font-medium">Edit</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-8 text-center text-sm text-gray-400">No past events yet.</div>
                @endif
            </div>

            {{-- ── ACTIVITY FEEDS ───────────────────────────────────────────── --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- Recent Registrations --}}
                <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800">Recent Registrations</h3>
                        <a href="{{ route('organizer.events.index') }}"
                           class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View events →</a>
                    </div>
                    @if ($recentRegistrations->count() > 0)
                        <ul class="divide-y divide-gray-50">
                            @foreach ($recentRegistrations as $reg)
                                <li class="px-6 py-3 flex items-start gap-3">
                                    <div class="mt-0.5 w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 shrink-0 text-xs font-bold">
                                        {{ strtoupper(substr($reg->user->name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-medium text-sm text-gray-900 truncate">{{ $reg->user->name }}</div>
                                        <div class="text-xs text-gray-500 truncate">{{ $reg->event->title }}</div>
                                        <div class="text-xs text-gray-400 mt-0.5">
                                            {{ $reg->ticketType->name }} &bull; {{ $reg->created_at->diffForHumans() }}
                                        </div>
                                    </div>
                                    <span class="ml-auto shrink-0 text-xs px-2 py-0.5 rounded-full font-medium
                                        {{ $reg->status === \App\Enums\RegistrationStatus::Attended ? 'bg-blue-100 text-blue-700'
                                            : ($reg->status === \App\Enums\RegistrationStatus::Cancelled ? 'bg-rose-100 text-rose-700'
                                            : 'bg-emerald-100 text-emerald-700') }}">
                                        {{ $reg->status->label() }}
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
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800">Recent Check-ins</h3>
                        <a href="{{ route('organizer.events.index') }}"
                           class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View events →</a>
                    </div>
                    @if ($recentCheckIns->count() > 0)
                        <ul class="divide-y divide-gray-50">
                            @foreach ($recentCheckIns as $checkIn)
                                <li class="px-6 py-3 flex items-start gap-3">
                                    <div class="mt-0.5 w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-medium text-sm text-gray-900 truncate">
                                            {{ $checkIn->registration->user->name }}
                                        </div>
                                        <div class="text-xs text-gray-500 truncate">
                                            {{ $checkIn->registration->event->title }}
                                        </div>
                                        <div class="text-xs text-gray-400 mt-0.5">
                                            {{ $checkIn->registration->ticketType->name }}
                                            &bull; {{ $checkIn->checked_in_at->diffForHumans() }}
                                        </div>
                                    </div>
                                    <div class="ml-auto text-right shrink-0">
                                        <div class="text-xs text-gray-400">by</div>
                                        <div class="text-xs font-medium text-gray-600">{{ $checkIn->checker->name }}</div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="p-8 text-center text-sm text-gray-400">No check-ins recorded yet.</div>
                    @endif
                </div>
            </div>

            {{-- ── QUICK ACTIONS ────────────────────────────────────────────── --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">Quick Actions</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    <a href="{{ route('organizer.events.create') }}"
                       class="flex flex-col items-center gap-2 p-4 rounded-xl border border-gray-100 hover:border-indigo-200 hover:bg-indigo-50 transition group text-center">
                        <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 group-hover:bg-indigo-200 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </div>
                        <span class="text-xs font-semibold text-gray-700 group-hover:text-indigo-700">Create Event</span>
                    </a>
                    <a href="{{ route('organizer.events.index') }}"
                       class="flex flex-col items-center gap-2 p-4 rounded-xl border border-gray-100 hover:border-emerald-200 hover:bg-emerald-50 transition group text-center">
                        <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 group-hover:bg-emerald-200 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <span class="text-xs font-semibold text-gray-700 group-hover:text-emerald-700">Manage Events</span>
                    </a>
                    <a href="{{ route('organizer.reports.index') }}"
                       class="flex flex-col items-center gap-2 p-4 rounded-xl border border-gray-100 hover:border-purple-200 hover:bg-purple-50 transition group text-center">
                        <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 group-hover:bg-purple-200 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <span class="text-xs font-semibold text-gray-700 group-hover:text-purple-700">Reports</span>
                    </a>
                    <a href="{{ route('events.index') }}"
                       class="flex flex-col items-center gap-2 p-4 rounded-xl border border-gray-100 hover:border-blue-200 hover:bg-blue-50 transition group text-center">
                        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 group-hover:bg-blue-200 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h.5A2.5 2.5 0 0021.5 5.5V3.935"/>
                            </svg>
                        </div>
                        <span class="text-xs font-semibold text-gray-700 group-hover:text-blue-700">Public Events</span>
                    </a>
                    <a href="{{ route('profile.edit') }}"
                       class="flex flex-col items-center gap-2 p-4 rounded-xl border border-gray-100 hover:border-gray-300 hover:bg-gray-50 transition group text-center">
                        <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-600 group-hover:bg-gray-200 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <span class="text-xs font-semibold text-gray-700 group-hover:text-gray-900">My Profile</span>
                    </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

