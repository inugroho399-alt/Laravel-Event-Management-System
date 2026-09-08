<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <a href="{{ route('organizer.events.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Events
                    </a>
                    <span class="text-xs text-gray-300">•</span>
                    <span class="text-xs text-gray-500 font-medium">{{ $event->title }}</span>
                </div>
                <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                    {{ __('Event Attendees') }}
                </h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ __('Review registered participants, ticket tiers, and attendance statuses.') }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('organizer.events.tickets.index', $event) }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition shadow-sm">
                    Manage Tickets
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Stat Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Registrations</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['total']) }}</div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Confirmed</div>
                    <div class="text-2xl font-bold text-indigo-600 mt-1">{{ number_format($stats['confirmed']) }}</div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Checked In</div>
                    <div class="text-2xl font-bold text-emerald-600 mt-1">{{ number_format($stats['attended']) }}</div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Cancelled</div>
                    <div class="text-2xl font-bold text-rose-600 mt-1">{{ number_format($stats['cancelled']) }}</div>
                </div>
            </div>

            <!-- Attendees Table -->
            <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100">
                @if ($registrations->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                            <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-xs">
                                <tr>
                                    <th class="px-6 py-3.5 font-semibold">Attendee</th>
                                    <th class="px-6 py-3.5 font-semibold">Ticket Tier</th>
                                    <th class="px-6 py-3.5 font-semibold">Registration Code</th>
                                    <th class="px-6 py-3.5 font-semibold">Status</th>
                                    <th class="px-6 py-3.5 font-semibold">Check-in</th>
                                    <th class="px-6 py-3.5 font-semibold">Registered At</th>
                                    <th class="px-6 py-3.5 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach ($registrations as $registration)
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="font-semibold text-gray-900">{{ $registration->user->name }}</div>
                                            <div class="text-xs text-gray-400">{{ $registration->user->email }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="font-medium text-gray-900">{{ $registration->ticketType->name }}</span>
                                            <div class="text-xs text-gray-400">{{ $registration->ticketType->price > 0 ? '$' . number_format($registration->ticketType->price, 2) : 'Free' }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <a href="{{ route('registrations.show', $registration) }}" class="font-mono font-semibold text-xs bg-gray-100 hover:bg-gray-200 text-indigo-700 px-2 py-1 rounded transition inline-flex items-center gap-1" title="View Ticket">
                                                {{ $registration->registration_code }}
                                            </a>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @php
                                                $badgeClasses = match($registration->status) {
                                                    \App\Enums\RegistrationStatus::Confirmed => 'bg-emerald-100 text-emerald-800',
                                                    \App\Enums\RegistrationStatus::Attended => 'bg-blue-100 text-blue-800',
                                                    \App\Enums\RegistrationStatus::Cancelled => 'bg-rose-100 text-rose-800',
                                                    default => 'bg-gray-100 text-gray-800',
                                                };
                                            @endphp
                                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $badgeClasses }}">
                                                {{ $registration->status->label() }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-xs">
                                            @if ($registration->checkIn)
                                                <span class="text-emerald-600 font-medium flex items-center gap-1">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    {{ $registration->checkIn->checked_in_at->format('M d, h:i A') }}
                                                </span>
                                            @else
                                                <span class="text-gray-400 italic">Not checked in</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">
                                            {{ $registration->created_at->format('M d, Y • h:i A') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-xs">
                                            <a href="{{ route('registrations.show', $registration) }}" class="text-indigo-600 hover:text-indigo-900 font-semibold">
                                                View Ticket &rarr;
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100">
                        {{ $registrations->links() }}
                    </div>
                @else
                    <div class="p-12 text-center">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-1">No attendees registered yet</h3>
                        <p class="text-gray-500 text-sm max-w-sm mx-auto">
                            When participants register for your event tickets, their details will appear here.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

