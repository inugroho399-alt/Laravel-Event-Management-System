<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @auth
                @if (auth()->user()->isAdmin())
                    {{-- Administrators are redirected to the dedicated admin dashboard --}}
                    <script>window.location.href = "{{ route('admin.dashboard') }}";</script>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-gray-900">
                            Redirecting to Administrator dashboard…
                            <a href="{{ route('admin.dashboard') }}" class="text-indigo-600 underline ml-1">Click here if not redirected.</a>
                        </div>
                    </div>
                @elseif (auth()->user()->isOrganizer())
                    {{-- Organizers are redirected to the dedicated organizer dashboard --}}
                    <script>window.location.href = "{{ route('organizer.dashboard') }}";</script>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-gray-900">
                            Redirecting to your organizer dashboard…
                            <a href="{{ route('organizer.dashboard') }}" class="text-indigo-600 underline ml-1">Click here if not redirected.</a>
                        </div>
                    </div>
                @elseif (auth()->user()->isParticipant())
                    {{-- Participants see a modern dashboard with quick access --}}
                    <div class="space-y-6">
                        <!-- Welcome Hero Card -->
                        <div class="bg-gradient-to-r from-indigo-600 via-indigo-700 to-purple-700 rounded-3xl p-8 sm:p-10 text-white shadow-lg relative overflow-hidden">
                            <div class="absolute -right-10 -bottom-10 w-60 h-60 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
                            <div class="relative z-10 max-w-2xl space-y-4">
                                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/20 backdrop-blur-md text-xs font-semibold uppercase tracking-wider">
                                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                    <span>Participant Portal</span>
                                </div>
                                <h3 class="text-2xl sm:text-3xl font-extrabold tracking-tight">
                                    Welcome back, {{ auth()->user()->name }}!
                                </h3>
                                <p class="text-indigo-100 text-sm sm:text-base leading-relaxed">
                                    Discover upcoming conferences, workshops, and tech gatherings. Manage your bookings, access digital tickets, and view check-in passes anytime.
                                </p>
                                <div class="pt-2 flex gap-3 flex-wrap">
                                    <a href="{{ route('events.index') }}"
                                       class="inline-flex items-center gap-2 px-5 py-2.5 bg-white text-indigo-700 text-sm font-bold rounded-xl hover:bg-indigo-50 transition shadow-sm">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                        Browse Events
                                    </a>
                                    <a href="{{ route('registrations.index') }}"
                                       class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-500/40 border border-white/20 text-white text-sm font-bold rounded-xl hover:bg-indigo-500/60 transition backdrop-blur-sm shadow-sm">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                                        </svg>
                                        My Registrations
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Navigation Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                            <a href="{{ route('events.index') }}" class="group bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md hover:border-indigo-200 transition">
                                <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <h4 class="font-bold text-slate-900 text-base group-hover:text-indigo-600 transition-colors">Explore Events</h4>
                                <p class="text-slate-500 text-xs mt-1">Browse all published conferences, webinars, and meetups.</p>
                            </a>

                            <a href="{{ route('registrations.index') }}" class="group bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md hover:border-emerald-200 transition">
                                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                                    </svg>
                                </div>
                                <h4 class="font-bold text-slate-900 text-base group-hover:text-emerald-600 transition-colors">Digital Passes</h4>
                                <p class="text-slate-500 text-xs mt-1">View your registrations and scan QR codes for fast check-in.</p>
                            </a>

                            <a href="{{ route('profile.edit') }}" class="group bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md hover:border-purple-200 transition">
                                <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                </div>
                                <h4 class="font-bold text-slate-900 text-base group-hover:text-purple-600 transition-colors">Account Settings</h4>
                                <p class="text-slate-500 text-xs mt-1">Update your profile info, email preferences, and password.</p>
                            </a>
                        </div>
                    </div>
                @else
                    <div class="bg-white overflow-hidden shadow-xs rounded-2xl border border-slate-200 p-6 text-slate-900">
                        {{ __("You're logged in!") }}
                    </div>
                @endif
            @endauth
        </div>
    </div>
</x-app-layout>
