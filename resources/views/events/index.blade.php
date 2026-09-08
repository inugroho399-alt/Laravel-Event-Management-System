<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-bold text-2xl text-slate-900 leading-tight">
                    {{ __('Discover Events') }}
                </h2>
                <p class="text-sm text-slate-500 mt-0.5">
                    {{ __('Browse upcoming conferences, workshops, and gatherings.') }}
                </p>
            </div>
            @auth
                @if (Auth::user()->isOrganizer() || Auth::user()->isAdmin())
                    <a href="{{ route('organizer.events.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs uppercase tracking-wider transition shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ __('Create Event') }}
                    </a>
                @endif
            @endauth
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            {{-- ── HERO SECTION (When not searching) ───────────────────────── --}}
            @if(empty($search))
                <div class="relative bg-gradient-to-r from-slate-900 via-indigo-950 to-purple-950 rounded-3xl p-8 sm:p-12 text-white shadow-xl overflow-hidden">
                    <div class="absolute -right-16 -top-16 w-80 h-80 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>
                    <div class="absolute right-1/4 -bottom-20 w-60 h-60 bg-purple-500/20 rounded-full blur-3xl pointer-events-none"></div>
                    
                    <div class="relative z-10 max-w-2xl space-y-4">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/20 border border-indigo-400/30 text-indigo-300 text-xs font-semibold uppercase tracking-wider">
                            <span class="w-2 h-2 rounded-full bg-indigo-400 animate-ping"></span>
                            Live Experience Hub
                        </div>
                        <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-white leading-tight">
                            Find your next extraordinary experience.
                        </h1>
                        <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
                            Discover conferences, masterclasses, and community meetups. Instant registrations with digital QR passes and real-time check-in.
                        </p>
                    </div>
                </div>
            @endif

            <!-- Search & Filters Bar -->
            <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-xs">
                <form method="GET" action="{{ route('events.index') }}" class="flex flex-col sm:flex-row gap-3">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input
                            type="text"
                            name="search"
                            value="{{ $search ?? '' }}"
                            placeholder="Search events by title, location, or keyword..."
                            class="block w-full pl-10 pr-4 py-2.5 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl shadow-xs text-sm"
                        />
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl transition shadow-xs">
                            Search
                        </button>
                        @if(!empty($search))
                            <a href="{{ route('events.index') }}" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl transition">
                                Reset
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <!-- Events Grid -->
            @if ($events->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                    @foreach ($events as $event)
                        <div class="group bg-white rounded-2xl shadow-xs hover:shadow-xl hover:-translate-y-1 transition-all duration-300 overflow-hidden border border-slate-200/80 flex flex-col">
                            <!-- Image / Header Banner -->
                            <div class="relative h-52 bg-gradient-to-tr from-slate-900 via-indigo-900 to-purple-800 overflow-hidden">
                                @if ($event->banner_image)
                                    <img src="{{ Storage::url($event->banner_image) }}" alt="{{ $event->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-white/30 group-hover:scale-105 transition-transform duration-500">
                                        <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                @endif
                                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                                <div class="absolute top-3.5 right-3.5">
                                    <x-status-badge :status="$event->status" size="sm" />
                                </div>
                                <div class="absolute bottom-3 left-4 text-xs font-semibold text-white/90 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    <span>{{ $event->organizer->name }}</span>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="p-6 flex-1 flex flex-col justify-between space-y-4">
                                <div>
                                    <div class="flex items-center gap-2 text-xs font-semibold text-indigo-600 mb-2.5">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span>{{ $event->start_date->format('M d, Y • h:i A') }}</span>
                                    </div>

                                    <h3 class="text-xl font-bold text-slate-900 group-hover:text-indigo-600 transition-colors line-clamp-1 mb-2">
                                        <a href="{{ route('events.show', $event) }}">
                                            {{ $event->title }}
                                        </a>
                                    </h3>

                                    <p class="text-slate-500 text-sm line-clamp-2 leading-relaxed">
                                        {{ $event->description ?: 'No description provided.' }}
                                    </p>
                                </div>

                                <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                                    <div class="flex items-center gap-1.5 truncate max-w-[180px]">
                                        <svg class="w-4 h-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span class="truncate">{{ $event->location ?: 'Online / TBD' }}</span>
                                    </div>

                                    <a href="{{ route('events.show', $event) }}" class="inline-flex items-center font-bold text-indigo-600 hover:text-indigo-700">
                                        View Details
                                        <svg class="w-4 h-4 ml-1 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $events->links() }}
                </div>
            @else
                <!-- Empty State -->
                <div class="bg-white rounded-3xl border border-dashed border-slate-300 p-12 text-center shadow-xs">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-1">No events found</h3>
                    <p class="text-slate-500 text-sm max-w-sm mx-auto mb-6">
                        @if(!empty($search))
                            No events match your search "{{ $search }}". Try different keywords or clear the search.
                        @else
                            There are currently no published events available. Check back soon!
                        @endif
                    </p>
                    @if(!empty($search))
                        <a href="{{ route('events.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl transition">
                            Clear Search
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
