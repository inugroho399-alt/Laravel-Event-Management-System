<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @auth
                @if (auth()->user()->isOrganizer() || auth()->user()->isAdmin())
                    {{-- Organizers and admins are redirected to the dedicated organizer dashboard --}}
                    <script>window.location.href = "{{ route('organizer.dashboard') }}";</script>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-gray-900">
                            Redirecting to your organizer dashboard…
                            <a href="{{ route('organizer.dashboard') }}" class="text-indigo-600 underline ml-1">Click here if not redirected.</a>
                        </div>
                    </div>
                @elseif (auth()->user()->isParticipant())
                    {{-- Participants see a summary of their registrations --}}
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-gray-900">
                            <h3 class="text-lg font-semibold mb-2">Welcome back, {{ auth()->user()->name }}!</h3>
                            <p class="text-gray-600 mb-4">Browse upcoming events and manage your registrations.</p>
                            <div class="flex gap-3 flex-wrap">
                                <a href="{{ route('events.index') }}"
                                   class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition">
                                    Browse Events
                                </a>
                                <a href="{{ route('registrations.index') }}"
                                   class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-semibold rounded-lg hover:bg-gray-50 transition">
                                    My Registrations
                                </a>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-gray-900">
                            {{ __("You're logged in!") }}
                        </div>
                    </div>
                @endif
            @endauth
        </div>
    </div>
</x-app-layout>
