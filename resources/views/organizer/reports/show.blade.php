<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <a href="{{ route('organizer.reports.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition">
                        &larr; {{ __('All Reports') }}
                    </a>
                    <span class="text-gray-300">/</span>
                    <span class="text-xs text-gray-500 font-medium">Event Analytics</span>
                </div>
                <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                    {{ $event->title }}
                </h2>
                <div class="flex items-center gap-3 text-xs text-gray-500 mt-1 flex-wrap">
                    <span>{{ $event->start_date?->format('M d, Y \a\t h:i A') }}</span>
                    <span>&bull;</span>
                    <span>{{ $event->location ?: 'Online Event' }}</span>
                    <span>&bull;</span>
                    @php
                        $statusClass = match ($event->status) {
                            \App\Enums\EventStatus::Published => 'bg-emerald-100 text-emerald-800',
                            \App\Enums\EventStatus::Ongoing   => 'bg-amber-100 text-amber-800',
                            \App\Enums\EventStatus::Completed => 'bg-blue-100 text-blue-800',
                            \App\Enums\EventStatus::Cancelled => 'bg-red-100 text-red-800',
                            default => 'bg-gray-100 text-gray-800',
                        };
                    @endphp
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $statusClass }}">
                        {{ $event->status->label() }}
                    </span>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('organizer.events.reports.export-attendees', $event) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-semibold text-xs uppercase tracking-widest transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    {{ __('Export Attendees CSV') }}
                </a>
                <a href="{{ route('organizer.events.check-in.create', $event) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 bg-purple-50 text-purple-700 border border-purple-200 rounded-lg text-xs font-semibold uppercase tracking-wider hover:bg-purple-100 transition">
                    {{ __('Check-in') }}
                </a>
                <a href="{{ route('organizer.events.registrations.index', $event) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg text-xs font-semibold uppercase tracking-wider hover:bg-indigo-100 transition">
                    {{ __('Attendees') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            {{-- ── EVENT KPI CARDS ─────────────────────────────────────────── --}}
            <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Capacity</div>
                    <div class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($totalCapacity) }}</div>
                    <div class="text-xs text-gray-400 mt-1">all ticket tiers</div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Registrations</div>
                    <div class="text-3xl font-bold text-indigo-600 mt-1">{{ number_format($activeRegistrationsCount) }}</div>
                    <div class="text-xs text-indigo-500/80 mt-1">{{ $capacityUtilization }}% of capacity</div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Attended</div>
                    <div class="text-3xl font-bold text-emerald-600 mt-1">{{ number_format($attendedCount) }}</div>
                    <div class="text-xs text-emerald-600 mt-1">checked in</div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Attendance Rate</div>
                    <div class="text-3xl font-bold text-blue-600 mt-1">{{ $attendanceRate }}%</div>
                    <div class="text-xs text-blue-500/80 mt-1">turnout percentage</div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Cancelled</div>
                    <div class="text-3xl font-bold text-rose-600 mt-1">{{ number_format($cancelledCount) }}</div>
                    <div class="text-xs text-rose-500/80 mt-1">voided tickets</div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Revenue</div>
                    <div class="text-3xl font-bold text-amber-600 mt-1">${{ number_format($totalRevenue, 2) }}</div>
                    <div class="text-xs text-amber-500/80 mt-1">active sales</div>
                </div>
            </div>

            {{-- ── REGISTRATION STATUS BREAKDOWN ────────────────────────────── --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Confirmed (Pending)</div>
                        <div class="text-2xl font-bold text-gray-900 mt-0.5">{{ number_format($confirmedCount) }}</div>
                        <div class="text-xs text-gray-400">Registered, not yet checked in</div>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Attended (Present)</div>
                        <div class="text-2xl font-bold text-emerald-600 mt-0.5">{{ number_format($attendedCount) }}</div>
                        <div class="text-xs text-emerald-600/80">Successfully checked in via QR</div>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Cancelled</div>
                        <div class="text-2xl font-bold text-rose-600 mt-0.5">{{ number_format($cancelledCount) }}</div>
                        <div class="text-xs text-rose-500/80">Returned quotas & zero revenue</div>
                    </div>
                </div>
            </div>

            {{-- ── TICKET TIERS BREAKDOWN TABLE ────────────────────────────── --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-800">Ticket Tier Breakdown</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Quota allocation, sales, check-in conversion, and revenue per ticket type.</p>
                    </div>
                    <a href="{{ route('organizer.events.tickets.index', $event) }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                        Manage Tickets &rarr;
                    </a>
                </div>

                @if ($ticketTiers->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-3 text-left font-semibold">Tier Name</th>
                                    <th class="px-6 py-3 text-right font-semibold">Price</th>
                                    <th class="px-6 py-3 text-center font-semibold">Quota</th>
                                    <th class="px-6 py-3 text-center font-semibold">Sold</th>
                                    <th class="px-6 py-3 text-center font-semibold">Remaining</th>
                                    <th class="px-6 py-3 text-center font-semibold">Attended</th>
                                    <th class="px-6 py-3 text-center font-semibold">Turnout Rate</th>
                                    <th class="px-6 py-3 text-right font-semibold">Revenue</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 bg-white">
                                @foreach ($ticketTiers as $tier)
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-6 py-4 font-semibold text-gray-900">
                                            {{ $tier->name }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-gray-700">
                                            {{ $tier->price > 0 ? '$'.number_format($tier->price, 2) : 'Free' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-gray-600">
                                            {{ number_format($tier->quota) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center font-semibold text-indigo-600">
                                            {{ number_format($tier->sold) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-gray-600">
                                            {{ number_format($tier->remaining) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center font-semibold text-emerald-600">
                                            {{ number_format($tier->attended) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <div class="inline-flex items-center gap-2">
                                                <div class="w-16 bg-gray-200 rounded-full h-1.5">
                                                    <div class="bg-blue-600 h-1.5 rounded-full" style="width: {{ min(100, $tier->attendance_rate) }}%"></div>
                                                </div>
                                                <span class="text-xs font-semibold text-gray-700">{{ $tier->attendance_rate }}%</span>
                                            </div>
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
                    <div class="p-8 text-center text-sm text-gray-400">No ticket tiers created for this event.</div>
                @endif
            </div>

            {{-- ── HOURLY CHECK-IN TIMELINE ─────────────────────────────────── --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100 p-6">
                <h3 class="font-semibold text-gray-800">Check-in Traffic Distribution</h3>
                <p class="text-xs text-gray-400 mt-0.5 mb-6">Volume of check-ins grouped by hour.</p>

                @if ($checkInTimeline->count() > 0)
                    <div class="space-y-3">
                        @php
                            $maxCount = max(1, $checkInTimeline->max('count'));
                        @endphp
                        @foreach ($checkInTimeline as $item)
                            @php
                                $percent = round(($item->count / $maxCount) * 100);
                            @endphp
                            <div class="flex items-center gap-4 text-xs">
                                <span class="w-36 text-gray-600 font-medium shrink-0">{{ $item->hour_bucket }}</span>
                                <div class="flex-1 bg-gray-100 rounded-full h-3 overflow-hidden">
                                    <div class="bg-indigo-600 h-3 rounded-full transition-all duration-500" style="width: {{ $percent }}%"></div>
                                </div>
                                <span class="w-16 text-right font-bold text-gray-900 shrink-0">{{ $item->count }} check-ins</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-8 text-center text-sm text-gray-400">No check-ins recorded for this event yet.</div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>

