<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 print:hidden">
            <div class="flex items-center gap-3">
                <a href="{{ url()->previous() != url()->current() ? url()->previous() : route('registrations.index') }}" class="p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <div>
                    <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                        {{ __('Digital Ticket') }}
                    </h2>
                    <p class="text-sm text-gray-500 mt-0.5">
                        {{ __('Official event pass and attendee credential') }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button onclick="window.print()" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50 active:bg-gray-100 transition shadow-sm">
                    <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    {{ __('Print Ticket') }}
                </button>
                <a href="{{ route('registrations.index') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold uppercase tracking-widest transition shadow-sm">
                    {{ __('My Tickets') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Flash Messages -->
            @if (session('status'))
                <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center gap-3 print:hidden">
                    <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            <!-- Digital Ticket Card Container -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden relative print:shadow-none print:border-2 print:border-gray-800 print:rounded-none">
                <!-- Top Brand Banner -->
                <div class="bg-gradient-to-r from-indigo-600 via-indigo-700 to-purple-800 text-white p-6 sm:p-8 relative overflow-hidden">
                    <div class="absolute -right-8 -bottom-8 w-40 h-40 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
                    <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold bg-white/20 text-white uppercase tracking-wider mb-2">
                                {{ $registration->event->category ?? 'Event Pass' }}
                            </span>
                            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">
                                {{ $registration->event->title }}
                            </h1>
                            <p class="text-indigo-200 text-sm mt-1 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Organized by {{ $registration->event->organizer->name }}
                            </p>
                        </div>

                        <!-- Ticket Status Badge -->
                        <div class="shrink-0">
                            @php
                                $statusColor = match($registration->status) {
                                    \App\Enums\RegistrationStatus::Confirmed => 'bg-emerald-400/20 text-emerald-200 border-emerald-400/30',
                                    \App\Enums\RegistrationStatus::Attended => 'bg-blue-400/20 text-blue-200 border-blue-400/30',
                                    \App\Enums\RegistrationStatus::Cancelled => 'bg-rose-400/20 text-rose-200 border-rose-400/30',
                                    default => 'bg-gray-400/20 text-gray-200 border-gray-400/30',
                                };
                            @endphp
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider border {{ $statusColor }}">
                                {{ $registration->status->label() }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Ticket Body -->
                <div class="p-6 sm:p-8 space-y-6">
                    <!-- Registration Code Box -->
                    <div class="bg-gray-50 rounded-xl p-4 sm:p-5 border border-dashed border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider block">
                                Registration Identifier Code
                            </span>
                            <div class="text-2xl sm:text-3xl font-mono font-black text-gray-900 tracking-wider mt-0.5">
                                {{ $registration->registration_code }}
                            </div>
                        </div>

                        <div class="flex items-center gap-2 print:hidden" x-data="{ copied: false }">
                            <button @click="navigator.clipboard.writeText('{{ $registration->registration_code }}'); copied = true; setTimeout(() => copied = false, 2000)" class="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 rounded-lg text-xs font-medium transition shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                </svg>
                                <span x-text="copied ? 'Copied!' : 'Copy Code'">Copy Code</span>
                            </button>
                        </div>
                    </div>

                    <!-- Event Logistics Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                        <!-- Date & Time -->
                        <div class="flex items-start gap-3.5">
                            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider block">Date & Time</span>
                                <div class="text-sm font-semibold text-gray-900 mt-0.5">
                                    {{ $registration->event->start_date->format('l, F d, Y') }}
                                </div>
                                <div class="text-xs text-gray-500 mt-0.5">
                                    {{ $registration->event->start_date->format('h:i A') }} - {{ $registration->event->end_date->format('h:i A') }}
                                </div>
                            </div>
                        </div>

                        <!-- Location -->
                        <div class="flex items-start gap-3.5">
                            <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider block">Venue / Location</span>
                                <div class="text-sm font-semibold text-gray-900 mt-0.5">
                                    {{ $registration->event->location }}
                                </div>
                                <div class="text-xs text-gray-500 mt-0.5">
                                    Present this ticket at the check-in counter
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Decorative Perforated Divider -->
                    <div class="relative py-4">
                        <div class="border-t-2 border-dashed border-gray-200"></div>
                        <div class="absolute -left-10 sm:-left-12 -top-0.5 w-6 h-6 bg-gray-100 rounded-full print:hidden"></div>
                        <div class="absolute -right-10 sm:-right-12 -top-0.5 w-6 h-6 bg-gray-100 rounded-full print:hidden"></div>
                    </div>

                    <!-- Attendee & Ticket Tier Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 bg-gray-50/70 p-5 rounded-xl border border-gray-100">
                        <div>
                            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider block">Attendee Name</span>
                            <div class="text-sm font-bold text-gray-900 mt-0.5">{{ $registration->user->name }}</div>
                            <div class="text-xs text-gray-500 truncate">{{ $registration->user->email }}</div>
                        </div>

                        <div>
                            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider block">Ticket Tier</span>
                            <div class="text-sm font-bold text-indigo-700 mt-0.5">{{ $registration->ticketType->name }}</div>
                            <div class="text-xs text-gray-500">
                                {{ $registration->ticketType->price > 0 ? '$' . number_format($registration->ticketType->price, 2) : 'Free Admission' }}
                            </div>
                        </div>

                        <div>
                            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider block">Check-in Status</span>
                            @if ($registration->isCheckedIn())
                                <div class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 px-2 py-1 rounded-md mt-0.5">
                                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Checked In
                                </div>
                                <div class="text-[11px] text-gray-400 mt-0.5">
                                    {{ $registration->checkIn->checked_in_at->format('M d, Y • h:i A') }}
                                </div>
                            @elseif ($registration->status === \App\Enums\RegistrationStatus::Cancelled)
                                <div class="inline-flex items-center gap-1.5 text-xs font-semibold text-rose-700 bg-rose-50 px-2 py-1 rounded-md mt-0.5">
                                    <svg class="w-3.5 h-3.5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    Cancelled
                                </div>
                                <div class="text-[11px] text-gray-400 mt-0.5">Ticket released</div>
                            @else
                                <div class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-700 bg-amber-50 px-2 py-1 rounded-md mt-0.5">
                                    <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Awaiting Check-in
                                </div>
                                <div class="text-[11px] text-gray-400 mt-0.5">Scan at entrance</div>
                            @endif
                        </div>
                    </div>

                    <!-- Pass Verification Box (Ready for QR in Phase 9) -->
                    <div class="p-6 rounded-xl border border-gray-100 bg-gradient-to-b from-white to-gray-50 flex flex-col items-center justify-center text-center">
                        <div class="w-36 h-36 bg-white p-3 rounded-xl border-2 border-gray-800 shadow-sm flex flex-col items-center justify-center relative group">
                            <!-- Digital Pass Icon Placeholder for QR -->
                            <div class="w-full h-full border border-dashed border-gray-300 rounded flex flex-col items-center justify-center p-2 bg-gray-50">
                                <svg class="w-12 h-12 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                </svg>
                                <span class="font-mono text-[10px] font-bold text-gray-800 mt-1 uppercase">
                                    {{ substr($registration->registration_code, 0, 10) }}
                                </span>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-3 font-medium">
                            Scan code at entrance for attendee check-in.
                        </p>
                        <p class="text-[11px] text-gray-400 mt-0.5">
                            Registered on {{ $registration->created_at->format('M d, Y • h:i A') }}
                        </p>
                    </div>

                    <!-- Action Bar (hidden when printing) -->
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-gray-100 print:hidden">
                        <div class="text-xs text-gray-400">
                            Ticket #{{ $registration->id }} &bull; Valid for 1 admission
                        </div>

                        <div class="flex items-center gap-3">
                            <a href="{{ route('events.show', $registration->event) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                                View Event Details &rarr;
                            </a>

                            @if ($registration->status === \App\Enums\RegistrationStatus::Confirmed)
                                <form method="POST" action="{{ route('registrations.cancel', $registration) }}" class="inline" onsubmit="return confirm('Are you sure you want to cancel your registration? Your ticket quota will be released.');">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-800">
                                        Cancel Registration
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Print-only Stylesheet -->
    <style>
        @media print {
            body {
                background: white !important;
                color: black !important;
            }
            nav, header, .print\:hidden {
                display: none !important;
            }
            .max-w-3xl {
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
        }
    </style>
</x-app-layout>

