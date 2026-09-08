<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <a href="{{ route('events.index') }}" class="inline-flex items-center text-sm font-semibold text-slate-500 hover:text-slate-800 transition-colors">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to All Events
            </a>
            @can('update', $event)
                <a href="{{ route('organizer.events.edit', $event) }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold uppercase tracking-wider transition-colors shadow-xs">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Edit Event
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            <!-- Event Hero Banner -->
            <div class="relative h-80 sm:h-96 rounded-3xl overflow-hidden shadow-md bg-gradient-to-tr from-slate-900 via-indigo-950 to-purple-950">
                @if ($event->banner_image)
                    <img src="{{ Storage::url($event->banner_image) }}" alt="{{ $event->title }}" class="w-full h-full object-cover">
                @else
                    <div class="w-full h-full flex items-center justify-center text-white/20">
                        <svg class="w-24 h-24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                @endif
                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/40 to-transparent"></div>
                <div class="absolute bottom-6 left-6 right-6 sm:bottom-8 sm:left-8 sm:right-8 text-white">
                    <div class="mb-3">
                        <x-status-badge :status="$event->status" size="md" />
                    </div>
                    <h1 class="text-2xl sm:text-4xl font-extrabold tracking-tight text-white mb-2">
                        {{ $event->title }}
                    </h1>
                    <p class="text-sm sm:text-base text-slate-200 flex items-center gap-2">
                        Organized by <span class="font-bold text-white">{{ $event->organizer->name }}</span>
                    </p>
                </div>
            </div>

            <!-- Event Details Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left: Description & Info -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- Description Card -->
                    <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-xs border border-slate-200/80 space-y-4">
                        <h2 class="text-xl font-bold text-slate-900">About This Event</h2>
                        <div class="prose max-w-none text-slate-700 leading-relaxed whitespace-pre-line text-sm sm:text-base">
                            {{ $event->description ?: 'No event description provided yet.' }}
                        </div>
                    </div>

                    <!-- Available Tickets -->
                    <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-xs border border-slate-200/80 space-y-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-xl font-bold text-slate-900">Available Tickets</h2>
                                <p class="text-xs text-slate-500 mt-0.5">Select your preferred ticket tier below to secure your spot.</p>
                            </div>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700">{{ $event->ticketTypes->count() }} type(s)</span>
                        </div>

                        @php
                            $userRegistration = Auth::check()
                                ? $event->registrations()->where('user_id', Auth::id())->where('status', '!=', \App\Enums\RegistrationStatus::Cancelled->value)->first()
                                : null;
                        @endphp

                        @if ($userRegistration)
                            <div class="p-5 rounded-2xl bg-emerald-50/80 border border-emerald-200 text-emerald-900 text-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 shadow-xs">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <span class="font-bold text-slate-900 block">You are registered for this event!</span>
                                        <span class="text-xs text-emerald-700 block mt-0.5">Code: <strong>{{ $userRegistration->registration_code }}</strong> ({{ $userRegistration->ticketType->name }})</span>
                                    </div>
                                </div>
                                <a href="{{ route('registrations.show', $userRegistration) }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-xs whitespace-nowrap">
                                    View Digital Ticket &rarr;
                                </a>
                            </div>
                        @endif

                        @if ($event->ticketTypes->count() > 0)
                            <div class="space-y-4">
                                @foreach ($event->ticketTypes as $ticket)
                                    @php
                                        $remaining = $ticket->remainingQuota();
                                        $soldPct = $ticket->quota > 0 ? round((($ticket->quota - $remaining) / $ticket->quota) * 100) : 0;
                                    @endphp
                                    <div class="p-5 rounded-2xl border border-slate-200/80 bg-slate-50/40 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 hover:border-indigo-300 hover:bg-white transition-all shadow-2xs">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <h3 class="font-bold text-slate-900 text-base">{{ $ticket->name }}</h3>
                                                @if($ticket->isSoldOut())
                                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20">Sold Out</span>
                                                @endif
                                            </div>
                                            @if ($ticket->description)
                                                <p class="text-xs text-slate-500 mt-1 leading-relaxed">{{ $ticket->description }}</p>
                                            @endif
                                            <div class="mt-3 flex items-center gap-4 text-xs text-slate-500">
                                                <span>Total Quota: {{ $ticket->quota }}</span>
                                                <span>•</span>
                                                <span class="{{ $remaining <= 5 && $remaining > 0 ? 'text-amber-600 font-bold' : '' }}">Remaining: {{ $remaining }}</span>
                                            </div>
                                            <!-- Quota progress indicator -->
                                            <div class="mt-2 w-full max-w-xs bg-slate-200 rounded-full h-1.5 overflow-hidden">
                                                <div class="bg-indigo-600 h-1.5 rounded-full" style="width: {{ $soldPct }}%"></div>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-4 sm:flex-col sm:items-end justify-between shrink-0">
                                            <div class="text-right">
                                                <div class="text-2xl font-black text-slate-900">
                                                    {{ $ticket->price > 0 ? '$' . number_format($ticket->price, 2) : 'Free' }}
                                                </div>
                                            </div>

                                            <div>
                                                @if ($userRegistration)
                                                    <span class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-100 text-emerald-800">
                                                        Registered
                                                    </span>
                                                @elseif ($ticket->isSoldOut())
                                                    <span class="px-4 py-2 rounded-xl text-xs font-bold bg-rose-100 text-rose-800">
                                                        Sold Out
                                                    </span>
                                                @elseif (! $event->isRegistrationOpen())
                                                    <span class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 text-slate-500">
                                                        Registration Closed
                                                    </span>
                                                @elseif (Auth::guest())
                                                    <a href="{{ route('login') }}" class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition shadow-sm">
                                                        Log in to Register
                                                    </a>
                                                @else
                                                    <form method="POST" action="{{ route('events.register', $event) }}">
                                                        @csrf
                                                        <input type="hidden" name="ticket_type_id" value="{{ $ticket->id }}">
                                                        <button type="submit" class="inline-flex items-center px-4 py-2.5 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition shadow-sm">
                                                            Register Now
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-slate-500 italic">No tickets have been listed for this event yet.</p>
                        @endif
                    </div>
                </div>

                <!-- Right Sidebar: Event Overview -->
                <div class="space-y-6">
                    <div class="bg-white p-6 sm:p-7 rounded-3xl shadow-xs border border-slate-200/80 space-y-6 sticky top-28">
                        <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">Event Overview</h3>
                        
                        <!-- Date & Time -->
                        <div class="flex items-start gap-3.5">
                            <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Date & Time</div>
                                <div class="text-sm font-bold text-slate-900 mt-0.5">{{ $event->start_date->format('l, F j, Y') }}</div>
                                <div class="text-xs text-slate-500 mt-0.5">{{ $event->start_date->format('h:i A') }} - {{ $event->end_date->format('h:i A') }}</div>
                            </div>
                        </div>

                        <!-- Location -->
                        <div class="flex items-start gap-3.5">
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600 shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div>
                                <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Location</div>
                                <div class="text-sm font-bold text-slate-900 mt-0.5">{{ $event->location ?: 'Online Event' }}</div>
                            </div>
                        </div>

                        <!-- Organizer -->
                        <div class="flex items-start gap-3.5">
                            <div class="w-10 h-10 rounded-xl bg-purple-50 flex items-center justify-center text-purple-600 shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <div>
                                <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Organizer</div>
                                <div class="text-sm font-bold text-slate-900 mt-0.5">{{ $event->organizer->name }}</div>
                                <div class="text-xs text-slate-500 mt-0.5">{{ $event->organizer->email }}</div>
                            </div>
                        </div>

                        <!-- Check-in Guidance -->
                        <div class="pt-4 border-t border-slate-100 bg-slate-50 -mx-6 sm:-mx-7 -mb-6 sm:-mb-7 p-5 rounded-b-3xl text-xs text-slate-500 space-y-1.5">
                            <div class="font-bold text-slate-800 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Digital QR Check-in
                            </div>
                            <p>After registering, your official digital pass with an encrypted QR code will be generated instantly for mobile or paper check-in.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
