<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-bold text-2xl text-slate-900 leading-tight">
                    {{ __('My Registrations') }}
                </h2>
                <p class="text-sm text-slate-500 mt-0.5">
                    {{ __('View your registered events, access registration codes, and manage your tickets.') }}
                </p>
            </div>
            <a href="{{ route('events.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center gap-3 shadow-xs">
                    <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if ($errors->has('error'))
                <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center gap-3 shadow-xs">
                    <svg class="w-5 h-5 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ $errors->first('error') }}</span>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-xs rounded-3xl border border-slate-200/80">
                @if ($registrations->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                            <thead class="bg-slate-50/80 text-slate-500 uppercase tracking-wider text-xs">
                                <tr>
                                    <th class="px-6 py-4 font-bold">Event</th>
                                    <th class="px-6 py-4 font-bold">Ticket Tier</th>
                                    <th class="px-6 py-4 font-bold">Registration Code</th>
                                    <th class="px-6 py-4 font-bold">Status</th>
                                    <th class="px-6 py-4 font-bold">Registered At</th>
                                    <th class="px-6 py-4 font-bold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @foreach ($registrations as $registration)
                                    <tr class="hover:bg-slate-50/60 transition-colors">
                                        <td class="px-6 py-4">
                                            <a href="{{ route('events.show', $registration->event) }}" class="font-bold text-slate-900 hover:text-indigo-600 block transition-colors">
                                                {{ $registration->event->title }}
                                            </a>
                                            <div class="text-xs text-slate-400 mt-0.5">
                                                {{ $registration->event->start_date->format('M d, Y • h:i A') }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="font-semibold text-slate-900">{{ $registration->ticketType->name }}</div>
                                            <div class="text-xs text-slate-400">
                                                {{ $registration->ticketType->price > 0 ? '$' . number_format($registration->ticketType->price, 2) : 'Free' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <a href="{{ route('registrations.show', $registration) }}" class="font-mono font-bold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-xl text-xs transition inline-flex items-center gap-1.5" title="View Digital Ticket">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                                                </svg>
                                                {{ $registration->registration_code }}
                                            </a>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <x-status-badge :status="$registration->status" size="sm" />
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-500">
                                            {{ $registration->created_at->format('M d, Y') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-3">
                                            <a href="{{ route('registrations.show', $registration) }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">
                                                View Ticket
                                            </a>
                                            @if ($registration->status === \App\Enums\RegistrationStatus::Confirmed)
                                                <form method="POST" action="{{ route('registrations.cancel', $registration) }}" class="inline" onsubmit="return confirm('Are you sure you want to cancel your registration? Your ticket quota will be released.');">
                                                    @csrf
                                                    <button type="submit" class="text-xs font-bold text-rose-600 hover:text-rose-800">
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
                    <div class="px-6 py-4 border-t border-slate-100">
                        {{ $registrations->links() }}
                    </div>
                @else
                    <div class="p-12 text-center">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 mb-1">No event registrations found</h3>
                        <p class="text-slate-500 text-sm max-w-sm mx-auto mb-6">
                            You have not registered for any events yet. Explore upcoming events and secure your tickets today!
                        </p>
                        <a href="{{ route('events.index') }}" class="inline-flex items-center px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-xs transition">
                            Explore Events
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

