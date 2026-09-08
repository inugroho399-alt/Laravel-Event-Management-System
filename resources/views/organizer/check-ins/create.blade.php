<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('organizer.events.registrations.index', $event) }}" class="p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition" title="Back to Attendee Roster">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-indigo-100 text-indigo-800">
                            Check-in Workstation
                        </span>
                        <span class="text-xs text-gray-400">&bull;</span>
                        <span class="text-xs text-gray-500 font-medium">{{ $event->title }}</span>
                    </div>
                    <h2 class="font-semibold text-2xl text-gray-800 leading-tight mt-0.5">
                        {{ __('QR Check-in Station') }}
                    </h2>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('organizer.events.registrations.index', $event) }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition shadow-sm">
                    <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    {{ __('Attendee Roster') }}
                </a>
                <a href="{{ route('events.show', $event) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold uppercase tracking-widest transition shadow-sm">
                    {{ __('Event Page') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Flash & Error Messages -->
            @if (session('status'))
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center gap-3 shadow-sm animate-fade-in">
                    <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center shrink-0 text-emerald-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div class="font-medium">
                        {{ session('status') }}
                    </div>
                </div>
            @endif

            @if ($errors->has('registration_code'))
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center gap-3 shadow-sm">
                    <div class="w-8 h-8 rounded-full bg-rose-100 flex items-center justify-center shrink-0 text-rose-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                    <div>
                        <span class="font-semibold block">Check-in Failed</span>
                        <span class="text-xs text-rose-700">{{ $errors->first('registration_code') }}</span>
                    </div>
                </div>
            @endif

            <!-- Live Attendance Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider block">Total Registered</span>
                    <div class="text-2xl font-bold text-gray-900 mt-1">{{ $totalRegistered }}</div>
                    <span class="text-xs text-gray-500 mt-1 block">Active participants</span>
                </div>

                <div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm">
                    <span class="text-xs font-semibold text-emerald-600 uppercase tracking-wider block">Checked In</span>
                    <div class="text-2xl font-bold text-emerald-700 mt-1">{{ $checkedInCount }}</div>
                    <span class="text-xs text-emerald-600 mt-1 block">{{ $percentage }}% attended</span>
                </div>

                <div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm">
                    <span class="text-xs font-semibold text-amber-600 uppercase tracking-wider block">Pending Check-in</span>
                    <div class="text-2xl font-bold text-amber-700 mt-1">{{ $pendingCount }}</div>
                    <span class="text-xs text-gray-500 mt-1 block">Awaiting arrival</span>
                </div>

                <div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm flex flex-col justify-center">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider block mb-1">Check-in Progress</span>
                    <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                        <div class="bg-emerald-500 h-3 rounded-full transition-all duration-500" style="width: {{ $percentage }}%"></div>
                    </div>
                    <span class="text-[11px] text-gray-500 text-right mt-1 font-mono font-medium">{{ $checkedInCount }} / {{ $totalRegistered }} Attendees</span>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Check-in Scanner & Input Form (Left 7 Cols) -->
                <div class="lg:col-span-7 space-y-6">
                    <div class="bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-sm">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Scan or Enter Registration Code</h3>
                                <p class="text-xs text-gray-500">Scan QR Code using a hardware scanner or type the attendee registration code</p>
                            </div>
                        </div>

                        <!-- Manual Code Form -->
                        <form method="POST" action="{{ route('organizer.events.check-in.store', $event) }}" class="space-y-4">
                            @csrf
                            <div>
                                <label for="registration_code" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">
                                    Registration Identifier Code
                                </label>
                                <div class="relative">
                                    <input
                                        type="text"
                                        name="registration_code"
                                        id="registration_code"
                                        required
                                        autofocus
                                        placeholder="EVENT-REG-XXXXXXXX"
                                        class="w-full text-lg sm:text-xl font-mono font-bold uppercase tracking-wider rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-3.5 pl-4 pr-12 text-gray-900"
                                        value="{{ old('registration_code') }}"
                                    >
                                    <div class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-gray-400">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="w-full py-3.5 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white rounded-xl font-semibold text-sm transition shadow-md flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ __('Check In Attendee') }}
                            </button>
                        </form>

                        <!-- Quick Tips Box -->
                        <div class="mt-6 p-4 rounded-xl bg-gray-50 border border-gray-200 text-xs text-gray-600 space-y-1.5">
                            <div class="font-semibold text-gray-800 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Check-in Tips:</span>
                            </div>
                            <p>&bull; Hardware 2D barcode scanners emulate keyboard typing and automatically submit the code.</p>
                            <p>&bull; Duplicate check-in attempts are rejected immediately by server-side verification.</p>
                            <p>&bull; Cancelled tickets are rejected and cannot gain admission.</p>
                        </div>
                    </div>
                </div>

                <!-- Recent Check-in Feed (Right 5 Cols) -->
                <div class="lg:col-span-5 space-y-6">
                    <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm flex flex-col h-full">
                        <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                            <h3 class="font-bold text-gray-900 text-base flex items-center gap-2">
                                <span>Recent Check-ins</span>
                                <span class="bg-emerald-100 text-emerald-800 text-xs font-semibold px-2 py-0.5 rounded-full">
                                    Live Feed
                                </span>
                            </h3>
                            <span class="text-xs text-gray-400">Last 10</span>
                        </div>

                        <div class="divide-y divide-gray-100 flex-1 overflow-y-auto max-h-[500px] mt-2">
                            @forelse ($recentCheckIns as $item)
                                <div class="py-3 flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="font-semibold text-sm text-gray-900 truncate">
                                            {{ $item->registration->user->name }}
                                        </div>
                                        <div class="text-xs text-gray-500 truncate">
                                            {{ $item->registration->ticketType->name }} &bull;
                                            <span class="font-mono text-gray-600">{{ $item->registration->registration_code }}</span>
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="text-xs font-semibold text-emerald-600 flex items-center gap-1 justify-end">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            {{ $item->checked_in_at->format('h:i:s A') }}
                                        </div>
                                        <div class="text-[10px] text-gray-400">
                                            by {{ $item->checker->name }}
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="py-12 text-center">
                                    <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-gray-100 text-gray-400 flex items-center justify-center">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div class="font-medium text-sm text-gray-700">No Check-ins Yet</div>
                                    <div class="text-xs text-gray-400 mt-0.5">Scanned attendees will appear in this live feed</div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
