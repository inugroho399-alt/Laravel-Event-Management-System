<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <a href="{{ route('admin.dashboard') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition">
                        &larr; {{ __('Admin Dashboard') }}
                    </a>
                    <span class="text-gray-300">/</span>
                    <span class="text-xs text-gray-500 font-medium">Registrations</span>
                </div>
                <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                    {{ __('Global Ticket Registrations') }}
                </h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ __('Monitor all ticket bookings, check-in status, and attendee records across the platform.') }}
                </p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- ── FILTER & SEARCH TOOLBAR ────────────────────────────────── --}}
            <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                <form method="GET" action="{{ route('admin.registrations.index') }}" class="flex flex-col sm:flex-row items-center gap-4 justify-between">
                    <div class="flex-1 w-full flex flex-col sm:flex-row items-center gap-3">
                        <div class="w-full sm:w-80">
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Search by code, attendee, or event..."
                                   class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <div class="w-full sm:w-48">
                            <select name="status" class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Statuses</option>
                                @foreach ($statuses as $st)
                                    <option value="{{ $st->value }}" @selected(request('status') === $st->value)>
                                        {{ $st->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg uppercase tracking-wider transition shadow-sm">
                            Filter
                        </button>

                        @if (request()->hasAny(['search', 'status']))
                            <a href="{{ route('admin.registrations.index') }}" class="text-xs text-gray-500 hover:text-gray-700 underline">
                                Clear
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- ── REGISTRATIONS TABLE ──────────────────────────────────────── --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100">
                @if ($registrations->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-3 text-left font-semibold">Code / Attendee</th>
                                    <th class="px-6 py-3 text-left font-semibold">Event</th>
                                    <th class="px-6 py-3 text-left font-semibold">Ticket Tier</th>
                                    <th class="px-6 py-3 text-center font-semibold">Status</th>
                                    <th class="px-6 py-3 text-left font-semibold">Check-in</th>
                                    <th class="px-6 py-3 text-right font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 bg-white">
                                @foreach ($registrations as $reg)
                                    @php
                                        $statusClass = match ($reg->status) {
                                            \App\Enums\RegistrationStatus::Confirmed => 'bg-indigo-50 text-indigo-700',
                                            \App\Enums\RegistrationStatus::Attended  => 'bg-emerald-50 text-emerald-700',
                                            \App\Enums\RegistrationStatus::Cancelled => 'bg-rose-50 text-rose-700',
                                        };
                                    @endphp
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="font-mono font-bold text-xs text-indigo-600">{{ $reg->registration_code }}</div>
                                            <div class="font-medium text-gray-900 text-sm mt-0.5">{{ $reg->user?->name }}</div>
                                            <div class="text-xs text-gray-400">{{ $reg->user?->email }}</div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="font-medium text-gray-900 text-sm">{{ $reg->event?->title }}</div>
                                            <div class="text-xs text-gray-400">{{ $reg->event?->start_date?->format('M d, Y') }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="font-medium text-gray-800 text-sm">{{ $reg->ticketType?->name }}</div>
                                            <div class="text-xs text-amber-600 font-semibold">
                                                {{ $reg->ticketType?->price > 0 ? '$'.number_format($reg->ticketType->price, 2) : 'Free' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $statusClass }}">
                                                {{ $reg->status->label() }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">
                                            @if ($reg->checkIn)
                                                <span class="text-emerald-700 font-medium">Checked In</span>
                                                <div class="text-gray-400">{{ $reg->checkIn->checked_in_at?->format('M d, H:i') }}</div>
                                            @else
                                                <span class="text-gray-400">Pending</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-xs space-x-3">
                                            @if ($reg->event)
                                                <a href="{{ route('organizer.events.reports.show', $reg->event) }}" class="text-indigo-600 hover:text-indigo-900 font-medium">Event Report</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($registrations->hasPages())
                        <div class="px-6 py-4 border-t border-gray-100">
                            {{ $registrations->links() }}
                        </div>
                    @endif
                @else
                    <div class="p-10 text-center text-gray-400 text-sm">
                        No registrations found matching your filter criteria.
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>

