<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                    {{ __('Reports & Analytics') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    {{ __('Financial metrics, attendance performance, and capacity utilization across your events.') }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('organizer.reports.export', request()->query()) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 active:bg-gray-100 transition shadow-sm">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    {{ __('Export CSV Summary') }}
                </a>
                <a href="{{ route('organizer.dashboard') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg font-semibold text-xs uppercase tracking-widest hover:bg-indigo-100 transition">
                    {{ __('Dashboard') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            {{-- ── FILTER TOOLBAR ────────────────────────────────────────── --}}
            <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                <form method="GET" action="{{ route('organizer.reports.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                    <div>
                        <label for="date_from" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1">
                            From Date
                        </label>
                        <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}"
                               class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="date_to" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1">
                            To Date
                        </label>
                        <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}"
                               class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="status" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1">
                            Status
                        </label>
                        <select id="status" name="status"
                                class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All Statuses</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="event_id" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1">
                            Event Filter
                        </label>
                        <select id="event_id" name="event_id"
                                class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All Events</option>
                            @foreach ($allEventsList as $ev)
                                <option value="{{ $ev->id }}" @selected((string) request('event_id') === (string) $ev->id)>
                                    {{ $ev->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="submit"
                                class="w-full inline-flex justify-center items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg uppercase tracking-wider transition shadow-sm">
                            {{ __('Filter') }}
                        </button>
                        @if (request()->hasAny(['date_from', 'date_to', 'status', 'event_id']))
                            <a href="{{ route('organizer.reports.index') }}"
                               class="px-3 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-semibold rounded-lg transition"
                               title="Clear filters">
                                {{ __('Clear') }}
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- ── AGGREGATE KPI CARDS ─────────────────────────────────────── --}}
            <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Events</div>
                    <div class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($totalEvents) }}</div>
                    <div class="text-xs text-gray-400 mt-1">in selected scope</div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Capacity</div>
                    <div class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($totalCapacity) }}</div>
                    <div class="text-xs text-gray-400 mt-1">allocated tickets</div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Registrations</div>
                    <div class="text-3xl font-bold text-indigo-600 mt-1">{{ number_format($totalRegistrations) }}</div>
                    <div class="text-xs text-indigo-500/80 mt-1">{{ $capacityUtilization }}% of capacity</div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Attended</div>
                    <div class="text-3xl font-bold text-emerald-600 mt-1">{{ number_format($totalAttended) }}</div>
                    <div class="text-xs text-emerald-600 mt-1">verified check-ins</div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Attendance Rate</div>
                    <div class="text-3xl font-bold text-blue-600 mt-1">{{ $overallAttendanceRate }}%</div>
                    <div class="text-xs text-blue-500/80 mt-1">of registered</div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm col-span-2 lg:col-span-1">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Revenue</div>
                    <div class="text-3xl font-bold text-amber-600 mt-1">${{ number_format($totalRevenue, 2) }}</div>
                    <div class="text-xs text-amber-500/80 mt-1">active tickets</div>
                </div>
            </div>

            {{-- ── EVENT PERFORMANCE BREAKDOWN TABLE ──────────────────────── --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-800">Event Performance Breakdown</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Click an event to view full metrics and attendee logs.</p>
                    </div>
                </div>

                @if ($events->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-3 text-left font-semibold">Event</th>
                                    <th class="px-6 py-3 text-left font-semibold">Schedule</th>
                                    <th class="px-6 py-3 text-left font-semibold">Status</th>
                                    <th class="px-6 py-3 text-center font-semibold">Capacity</th>
                                    <th class="px-6 py-3 text-center font-semibold">Registrations</th>
                                    <th class="px-6 py-3 text-center font-semibold">Attended</th>
                                    <th class="px-6 py-3 text-center font-semibold">Attendance Rate</th>
                                    <th class="px-6 py-3 text-right font-semibold">Revenue</th>
                                    <th class="px-6 py-3 text-right font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 bg-white">
                                @foreach ($events as $event)
                                    @php
                                        $statusClass = match ($event->status) {
                                            \App\Enums\EventStatus::Published => 'bg-emerald-100 text-emerald-800',
                                            \App\Enums\EventStatus::Ongoing   => 'bg-amber-100 text-amber-800',
                                            \App\Enums\EventStatus::Completed => 'bg-blue-100 text-blue-800',
                                            \App\Enums\EventStatus::Cancelled => 'bg-red-100 text-red-800',
                                            default => 'bg-gray-100 text-gray-800',
                                        };
                                    @endphp
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-6 py-4">
                                            <a href="{{ route('organizer.events.reports.show', $event) }}" class="font-semibold text-gray-900 hover:text-indigo-600 transition">
                                                {{ $event->title }}
                                            </a>
                                            <div class="text-xs text-gray-400">{{ $event->location ?: 'Online' }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-600 text-xs">
                                            <div>{{ $event->start_date?->format('M d, Y') }}</div>
                                            <div class="text-gray-400">{{ $event->start_date?->format('h:i A') }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $statusClass }}">
                                                {{ $event->status->label() }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-gray-700 font-medium">
                                            {{ number_format($event->total_capacity) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-gray-700 font-medium">
                                            {{ number_format($event->active_registrations_count) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-emerald-600 font-semibold">
                                            {{ number_format($event->attended_count) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <div class="inline-flex items-center gap-2">
                                                <div class="w-16 bg-gray-200 rounded-full h-1.5">
                                                    <div class="bg-blue-600 h-1.5 rounded-full" style="width: {{ min(100, $event->attendance_rate) }}%"></div>
                                                </div>
                                                <span class="text-xs font-semibold text-gray-700">{{ $event->attendance_rate }}%</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right font-semibold text-amber-600">
                                            ${{ number_format($event->revenue, 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-xs space-x-3">
                                            <a href="{{ route('organizer.events.reports.show', $event) }}"
                                               class="text-indigo-600 hover:text-indigo-800 font-medium">Detailed Report</a>
                                            <a href="{{ route('organizer.events.reports.export-attendees', $event) }}"
                                               class="text-emerald-600 hover:text-emerald-800 font-medium">Export CSV</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($events->hasPages())
                        <div class="px-6 py-4 border-t border-gray-100">
                            {{ $events->links() }}
                        </div>
                    @endif
                @else
                    <div class="p-10 text-center">
                        <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-semibold text-gray-700">No events found</p>
                        <p class="text-xs text-gray-400 mt-1">Try adjusting your filters or organize your first event.</p>
                    </div>
                @endif
            </div>

            {{-- ── TICKET TIER PERFORMANCE BREAKDOWN ──────────────────────── --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Ticket Tier Performance</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Quota fulfillment and revenue generation by ticket type.</p>
                </div>

                @if ($ticketTiers->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-3 text-left font-semibold">Ticket Tier</th>
                                    <th class="px-6 py-3 text-left font-semibold">Event</th>
                                    <th class="px-6 py-3 text-right font-semibold">Price</th>
                                    <th class="px-6 py-3 text-center font-semibold">Quota</th>
                                    <th class="px-6 py-3 text-center font-semibold">Sold</th>
                                    <th class="px-6 py-3 text-center font-semibold">Remaining</th>
                                    <th class="px-6 py-3 text-center font-semibold">Attended</th>
                                    <th class="px-6 py-3 text-right font-semibold">Revenue</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 bg-white">
                                @foreach ($ticketTiers as $tier)
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-6 py-4 font-semibold text-gray-900">
                                            {{ $tier->name }}
                                        </td>
                                        <td class="px-6 py-4 text-gray-600 text-xs">
                                            {{ $tier->event?->title }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-gray-700">
                                            {{ $tier->price > 0 ? '$'.number_format($tier->price, 2) : 'Free' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-gray-600">
                                            {{ number_format($tier->quota) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center font-medium text-indigo-600">
                                            {{ number_format($tier->active_registrations_count) }}
                                            <span class="text-xs text-gray-400 font-normal">({{ $tier->sold_percentage }}%)</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-gray-600">
                                            {{ number_format($tier->remaining) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-emerald-600 font-medium">
                                            {{ number_format($tier->attended_count) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right font-semibold text-amber-600">
                                            ${{ number_format($tier->revenue, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-8 text-center text-sm text-gray-400">No ticket tiers found in this scope.</div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
