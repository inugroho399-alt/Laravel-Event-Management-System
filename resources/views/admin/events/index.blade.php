<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <a href="{{ route('admin.dashboard') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition">
                        &larr; {{ __('Admin Dashboard') }}
                    </a>
                    <span class="text-gray-300">/</span>
                    <span class="text-xs text-gray-500 font-medium">All Events</span>
                </div>
                <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                    {{ __('Global Events Oversight') }}
                </h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ __('Inspect, moderate status, and manage all events organized across the platform.') }}
                </p>
            </div>
            <a href="{{ route('organizer.events.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold text-xs uppercase tracking-widest transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('Create Event') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center gap-3">
                    <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            {{-- ── FILTER TOOLBAR ────────────────────────────────────────── --}}
            <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                <form method="GET" action="{{ route('admin.events.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Search title or location..."
                               class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1">Status</label>
                        <select name="status" class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All Statuses</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1">Organizer</label>
                        <select name="organizer_id" class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All Organizers</option>
                            @foreach ($organizers as $org)
                                <option value="{{ $org->id }}" @selected((string) request('organizer_id') === (string) $org->id)>
                                    {{ $org->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="submit"
                                class="w-full inline-flex justify-center items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg uppercase tracking-wider transition shadow-sm">
                            Filter
                        </button>
                        @if (request()->hasAny(['search', 'status', 'organizer_id']))
                            <a href="{{ route('admin.events.index') }}"
                               class="px-3 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-semibold rounded-lg transition"
                               title="Clear filters">
                                Clear
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- ── EVENTS TABLE ────────────────────────────────────────────── --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100">
                @if ($events->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-3 text-left font-semibold">Event</th>
                                    <th class="px-6 py-3 text-left font-semibold">Organizer</th>
                                    <th class="px-6 py-3 text-left font-semibold">Schedule</th>
                                    <th class="px-6 py-3 text-left font-semibold">Status / Moderation</th>
                                    <th class="px-6 py-3 text-center font-semibold">Registrations</th>
                                    <th class="px-6 py-3 text-right font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 bg-white">
                                @foreach ($events as $ev)
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-6 py-4">
                                            <a href="{{ route('organizer.events.reports.show', $ev) }}" class="font-semibold text-gray-900 hover:text-indigo-600 transition">
                                                {{ $ev->title }}
                                            </a>
                                            <div class="text-xs text-gray-400 mt-0.5">{{ $ev->location ?: 'Online Event' }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="font-medium text-gray-900 text-xs">{{ $ev->organizer?->name }}</div>
                                            <div class="text-xs text-gray-400">{{ $ev->organizer?->email }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-600 text-xs">
                                            <div>{{ $ev->start_date?->format('M d, Y') }}</div>
                                            <div class="text-gray-400">{{ $ev->start_date?->format('h:i A') }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <form method="POST" action="{{ route('admin.events.update-status', $ev) }}" class="flex items-center gap-2">
                                                @csrf
                                                @method('PATCH')
                                                <select name="status" onchange="this.form.submit()"
                                                        class="text-xs rounded-lg border-gray-300 py-1 pl-2 pr-7 font-semibold focus:border-indigo-500 focus:ring-indigo-500
                                                        {{ $ev->status === \App\Enums\EventStatus::Published ? 'bg-emerald-50 text-emerald-800'
                                                            : ($ev->status === \App\Enums\EventStatus::Ongoing ? 'bg-amber-50 text-amber-800'
                                                            : ($ev->status === \App\Enums\EventStatus::Completed ? 'bg-blue-50 text-blue-800'
                                                            : ($ev->status === \App\Enums\EventStatus::Cancelled ? 'bg-rose-50 text-rose-800'
                                                            : 'bg-gray-50 text-gray-800'))) }}">
                                                    @foreach ($statuses as $st)
                                                        <option value="{{ $st->value }}" @selected($ev->status === $st)>
                                                            {{ $st->label() }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-gray-700 font-medium">
                                            {{ number_format($ev->registrations_count) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-xs space-x-3">
                                            <a href="{{ route('organizer.events.reports.show', $ev) }}" class="text-indigo-600 hover:text-indigo-900 font-medium">Report</a>
                                            <a href="{{ route('events.show', $ev) }}" class="text-blue-600 hover:text-blue-900 font-medium">Public</a>
                                            <a href="{{ route('organizer.events.edit', $ev) }}" class="text-gray-600 hover:text-gray-900 font-medium">Edit</a>
                                            <form method="POST" action="{{ route('admin.events.destroy', $ev) }}" class="inline"
                                                  onsubmit="return confirm('Are you sure you want to delete event \'{{ $ev->title }}\'? This action is irreversible.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-rose-600 hover:text-rose-900 font-medium">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($events->hasPages())
                        <div class="px-6 py-4 border-t border-gray-100">
                            {{ $events->links() }}
                        </div>
                    @endif
                @else
                    <div class="p-10 text-center text-gray-400 text-sm">
                        No events found matching your filter criteria.
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>

