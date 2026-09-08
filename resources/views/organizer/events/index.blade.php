<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                    {{ __('Manage Events') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    {{ __('Create, edit, and monitor your organized events.') }}
                </p>
            </div>
            <a href="{{ route('organizer.events.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 transition shadow-sm">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('New Event') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center gap-3">
                    <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100">
                @if ($events->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                            <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-xs">
                                <tr>
                                    <th class="px-6 py-3 font-semibold">Event</th>
                                    <th class="px-6 py-3 font-semibold">Schedule</th>
                                    <th class="px-6 py-3 font-semibold">Status</th>
                                    <th class="px-6 py-3 font-semibold">Tickets</th>
                                    <th class="px-6 py-3 font-semibold">Registrations</th>
                                    <th class="px-6 py-3 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach ($events as $event)
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="font-semibold text-gray-900">{{ $event->title }}</div>
                                            <div class="text-xs text-gray-400 mt-0.5">{{ $event->location ?: 'Online' }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-600 text-xs">
                                            <div>{{ $event->start_date->format('M d, Y') }}</div>
                                            <div class="text-gray-400">{{ $event->start_date->format('h:i A') }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <x-status-badge :status="$event->status" />
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                            {{ $event->ticket_types_count }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                            {{ $event->registrations_count }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-2">
                                            <a href="{{ route('organizer.events.check-in.create', $event) }}" class="text-purple-600 hover:text-purple-800 font-medium">Check-in</a>
                                            <a href="{{ route('organizer.events.tickets.index', $event) }}" class="text-amber-600 hover:text-amber-800 font-medium">Tickets ({{ $event->ticket_types_count }})</a>
                                            <a href="{{ route('organizer.events.registrations.index', $event) }}" class="text-emerald-600 hover:text-emerald-800 font-medium">Attendees ({{ $event->registrations_count }})</a>
                                            <a href="{{ route('organizer.events.reports.show', $event) }}" class="text-blue-600 hover:text-blue-800 font-medium">Report</a>
                                            <a href="{{ route('events.show', $event) }}" class="text-indigo-600 hover:text-indigo-900 font-medium">View</a>
                                            <a href="{{ route('organizer.events.edit', $event) }}" class="text-gray-600 hover:text-gray-900 font-medium">Edit</a>
                                            <form method="POST" action="{{ route('organizer.events.destroy', $event) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete this event?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900 font-medium">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100">
                        {{ $events->links() }}
                    </div>
                @else
                    <div class="p-12 text-center">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-1">No events organized yet</h3>
                        <p class="text-gray-500 text-sm max-w-sm mx-auto mb-6">
                            Start by creating your first event to manage tickets, accept registrations, and check-in attendees.
                        </p>
                        <a href="{{ route('organizer.events.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-sm transition">
                            Create Event
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
