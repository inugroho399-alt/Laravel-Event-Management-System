<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <a href="{{ route('events.index') }}" class="inline-flex items-center text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to All Events
            </a>
            @can('update', $event)
                <a href="{{ route('organizer.events.edit', $event) }}" class="inline-flex items-center px-3.5 py-1.5 bg-gray-900 hover:bg-gray-800 text-white rounded-lg text-xs font-semibold uppercase tracking-wider transition-colors shadow-sm">
                    <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Edit Event
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            <!-- Event Hero Banner -->
            <div class="relative h-72 sm:h-96 rounded-2xl overflow-hidden shadow-sm bg-gradient-to-tr from-indigo-900 via-indigo-700 to-purple-600">
                @if ($event->banner_image)
                    <img src="{{ Storage::url($event->banner_image) }}" alt="{{ $event->title }}" class="w-full h-full object-cover">
                @else
                    <div class="w-full h-full flex items-center justify-center text-white/30">
                        <svg class="w-24 h-24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                @endif
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"></div>
                <div class="absolute bottom-6 left-6 right-6 text-white">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-indigo-500/80 backdrop-blur-md mb-3">
                        {{ $event->status->label() }}
                    </span>
                    <h1 class="text-2xl sm:text-4xl font-bold tracking-tight text-white mb-2">
                        {{ $event->title }}
                    </h1>
                    <p class="text-sm sm:text-base text-gray-200 flex items-center gap-2">
                        Organized by <span class="font-semibold text-white">{{ $event->organizer->name }}</span>
                    </p>
                </div>
            </div>

            <!-- Event Details Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left: Description & Info -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white p-6 sm:p-8 rounded-xl shadow-sm border border-gray-100 space-y-6">
                        <h2 class="text-xl font-bold text-gray-900">About This Event</h2>
                        <div class="prose max-w-none text-gray-700 leading-relaxed whitespace-pre-line">
                            {{ $event->description ?: 'No event description provided yet.' }}
                        </div>
                    </div>

                    <!-- Available Tickets -->
                    <div class="bg-white p-6 sm:p-8 rounded-xl shadow-sm border border-gray-100 space-y-6">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-bold text-gray-900">Available Tickets</h2>
                            <span class="text-xs font-medium text-gray-500">{{ $event->ticketTypes->count() }} type(s)</span>
                        </div>

                        @if ($event->ticketTypes->count() > 0)
                            <div class="space-y-4">
                                @foreach ($event->ticketTypes as $ticket)
                                    <div class="p-4 rounded-xl border border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 hover:border-indigo-200 transition-colors">
                                        <div>
                                            <h3 class="font-semibold text-gray-900">{{ $ticket->name }}</h3>
                                            @if ($ticket->description)
                                                <p class="text-sm text-gray-500 mt-0.5">{{ $ticket->description }}</p>
                                            @endif
                                            <div class="flex items-center gap-3 text-xs text-gray-400 mt-2">
                                                <span>Total Quota: {{ $ticket->quota }}</span>
                                                <span>•</span>
                                                <span>Remaining: {{ $ticket->remainingQuota() }}</span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-4">
                                            <div class="text-right">
                                                <div class="text-lg font-bold text-gray-900">
                                                    {{ $ticket->price > 0 ? '$' . number_format($ticket->price, 2) : 'Free' }}
                                                </div>
                                            </div>
                                            @if ($ticket->isSoldOut())
                                                <span class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-gray-100 text-gray-500">
                                                    Sold Out
                                                </span>
                                            @elseif ($event->isRegistrationOpen())
                                                <button type="button" disabled class="px-4 py-2 rounded-lg text-xs font-semibold bg-indigo-600 text-white opacity-80 cursor-not-allowed">
                                                    Registration (Phase 7)
                                                </button>
                                            @else
                                                <span class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-gray-100 text-gray-500">
                                                    Registration Closed
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-500 italic">No tickets have been listed for this event yet.</p>
                        @endif
                    </div>
                </div>

                <!-- Right Sidebar: Quick Facts -->
                <div class="space-y-6">
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 space-y-6">
                        <h3 class="text-base font-semibold text-gray-900 border-b border-gray-100 pb-3">Event Overview</h3>
                        
                        <!-- Date & Time -->
                        <div class="flex items-start gap-3">
                            <div class="w-9 h-9 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600 shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <div class="text-xs font-medium text-gray-400">Date & Time</div>
                                <div class="text-sm font-semibold text-gray-900 mt-0.5">{{ $event->start_date->format('l, F j, Y') }}</div>
                                <div class="text-xs text-gray-500">{{ $event->start_date->format('h:i A') }} - {{ $event->end_date->format('h:i A') }}</div>
                            </div>
                        </div>

                        <!-- Location -->
                        <div class="flex items-start gap-3">
                            <div class="w-9 h-9 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600 shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div>
                                <div class="text-xs font-medium text-gray-400">Location</div>
                                <div class="text-sm font-semibold text-gray-900 mt-0.5">{{ $event->location ?: 'Online Event' }}</div>
                            </div>
                        </div>

                        <!-- Organizer -->
                        <div class="flex items-start gap-3">
                            <div class="w-9 h-9 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600 shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <div>
                                <div class="text-xs font-medium text-gray-400">Organizer</div>
                                <div class="text-sm font-semibold text-gray-900 mt-0.5">{{ $event->organizer->name }}</div>
                                <div class="text-xs text-gray-500">{{ $event->organizer->email }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
