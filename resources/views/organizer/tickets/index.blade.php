<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <a href="{{ route('organizer.events.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Events
                    </a>
                    <span class="text-xs text-gray-300">•</span>
                    <span class="text-xs text-gray-500 font-medium">{{ $event->title }}</span>
                </div>
                <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                    {{ __('Ticket Management') }}
                </h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ __('Configure ticket tiers, set prices, and monitor registration quotas.') }}
                </p>
            </div>
            <a href="{{ route('organizer.events.tickets.create', $event) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 transition shadow-sm">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('Add Ticket Type') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Alert Messages -->
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

            <!-- Summary Stat Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Ticket Types</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['total_types'] }}</div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Capacity</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['total_capacity']) }}</div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Registered</div>
                    <div class="text-2xl font-bold text-indigo-600 mt-1">{{ number_format($stats['total_registered']) }}</div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Remaining Seats</div>
                    <div class="text-2xl font-bold text-emerald-600 mt-1">{{ number_format($stats['total_remaining']) }}</div>
                </div>
            </div>

            <!-- Tickets Table -->
            <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100">
                @if ($ticketTypes->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                            <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-xs">
                                <tr>
                                    <th class="px-6 py-3.5 font-semibold">Tier Name</th>
                                    <th class="px-6 py-3.5 font-semibold">Price</th>
                                    <th class="px-6 py-3.5 font-semibold">Quota</th>
                                    <th class="px-6 py-3.5 font-semibold">Sold / Registered</th>
                                    <th class="px-6 py-3.5 font-semibold">Remaining</th>
                                    <th class="px-6 py-3.5 font-semibold">Availability</th>
                                    <th class="px-6 py-3.5 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach ($ticketTypes as $ticket)
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="font-semibold text-gray-900">{{ $ticket->name }}</div>
                                            @if ($ticket->description)
                                                <div class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ $ticket->description }}</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="font-bold text-gray-900">
                                                {{ $ticket->price > 0 ? '$' . number_format($ticket->price, 2) : 'Free' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-600 font-medium">
                                            {{ number_format($ticket->quota) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                            {{ number_format($ticket->active_registrations_count) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap font-medium {{ $ticket->remainingQuota() === 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                            {{ number_format($ticket->remainingQuota()) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if ($ticket->isSoldOut())
                                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-800">
                                                    Sold Out
                                                </span>
                                            @else
                                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                                    Available
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-2">
                                            <a href="{{ route('organizer.events.tickets.edit', [$event, $ticket]) }}" class="text-indigo-600 hover:text-indigo-900 font-medium">Edit</a>
                                            <form method="POST" action="{{ route('organizer.events.tickets.destroy', [$event, $ticket]) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete this ticket type?');">
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
                @else
                    <div class="p-12 text-center">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-1">No ticket types created yet</h3>
                        <p class="text-gray-500 text-sm max-w-sm mx-auto mb-6">
                            Create ticket categories such as General Admission, Early Bird, or VIP for your event.
                        </p>
                        <a href="{{ route('organizer.events.tickets.create', $event) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-sm transition">
                            Create First Ticket Type
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
