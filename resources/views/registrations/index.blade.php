<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                    {{ __('My Registrations') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    {{ __('View your registered events, access registration codes, and manage your tickets.') }}
                </p>
            </div>
            <a href="{{ route('events.index') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold uppercase tracking-widest transition shadow-sm">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                {{ __('Browse More Events') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Flash & Error Messages -->
            @if (session('status'))
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center gap-3">
                    <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if ($errors->has('error'))
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center gap-3">
                    <svg class="w-5 h-5 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ $errors->first('error') }}</span>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100">
                @if ($registrations->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                            <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-xs">
                                <tr>
                                    <th class="px-6 py-3.5 font-semibold">Event</th>
                                    <th class="px-6 py-3.5 font-semibold">Ticket Tier</th>
                                    <th class="px-6 py-3.5 font-semibold">Registration Code</th>
                                    <th class="px-6 py-3.5 font-semibold">Status</th>
                                    <th class="px-6 py-3.5 font-semibold">Registered At</th>
                                    <th class="px-6 py-3.5 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach ($registrations as $registration)
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-6 py-4">
                                            <a href="{{ route('events.show', $registration->event) }}" class="font-semibold text-indigo-600 hover:text-indigo-900 block">
                                                {{ $registration->event->title }}
                                            </a>
                                            <div class="text-xs text-gray-400 mt-0.5">
                                                {{ $registration->event->start_date->format('M d, Y • h:i A') }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="font-medium text-gray-900">{{ $registration->ticketType->name }}</div>
                                            <div class="text-xs text-gray-400">
                                                {{ $registration->ticketType->price > 0 ? '$' . number_format($registration->ticketType->price, 2) : 'Free' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <a href="{{ route('registrations.show', $registration) }}" class="font-mono font-bold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1 rounded text-xs transition inline-flex items-center gap-1.5" title="View Digital Ticket">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                                                </svg>
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
                                        <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">
                                            {{ $registration->created_at->format('M d, Y') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-3">
                                            <a href="{{ route('registrations.show', $registration) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                                                View Ticket
                                            </a>
                                            @if ($registration->status === \App\Enums\RegistrationStatus::Confirmed)
                                                <form method="POST" action="{{ route('registrations.cancel', $registration) }}" class="inline" onsubmit="return confirm('Are you sure you want to cancel your registration? Your ticket quota will be released.');">
                                                    @csrf
                                                    <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-800">
                                                        Cancel
                                                    </button>
                                                </form>
                                            @endif
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-1">No event registrations found</h3>
                        <p class="text-gray-500 text-sm max-w-sm mx-auto mb-6">
                            You have not registered for any events yet. Explore upcoming events and secure your tickets today!
                        </p>
                        <a href="{{ route('events.index') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-sm transition">
                            Explore Events
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

