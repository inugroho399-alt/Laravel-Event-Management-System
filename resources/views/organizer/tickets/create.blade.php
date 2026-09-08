<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <a href="{{ route('organizer.events.tickets.index', $event) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Ticket Types
            </a>
            <span class="text-xs text-gray-300">•</span>
            <span class="text-xs text-gray-500 font-medium">{{ $event->title }}</span>
        </div>
        <h2 class="font-semibold text-2xl text-gray-800 leading-tight mt-1">
            {{ __('Add New Ticket Type') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white p-6 sm:p-8 rounded-xl shadow-sm border border-gray-100">
                <form method="POST" action="{{ route('organizer.events.tickets.store', $event) }}" class="space-y-6">
                    @csrf

                    <!-- Ticket Name -->
                    <div>
                        <x-input-label for="name" :value="__('Ticket Name *')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" placeholder="e.g. VIP Access, General Admission, Early Bird" required autofocus />
                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                    </div>

                    <!-- Price & Quota Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <!-- Price -->
                        <div>
                            <x-input-label for="price" :value="__('Price (USD) *')" />
                            <div class="relative mt-1">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <span class="text-gray-500 sm:text-sm">$</span>
                                </div>
                                <x-text-input id="price" name="price" type="number" step="0.01" min="0" class="block w-full pl-7" :value="old('price', '0.00')" required />
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Enter 0.00 for free tickets.</p>
                            <x-input-error class="mt-2" :messages="$errors->get('price')" />
                        </div>

                        <!-- Quota -->
                        <div>
                            <x-input-label for="quota" :value="__('Total Quota / Seats *')" />
                            <x-text-input id="quota" name="quota" type="number" min="1" step="1" class="mt-1 block w-full" :value="old('quota', '100')" required />
                            <p class="text-xs text-gray-500 mt-1">Maximum number of tickets that can be registered.</p>
                            <x-input-error class="mt-2" :messages="$errors->get('quota')" />
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <x-input-label for="description" :value="__('Description (Optional)')" />
                        <textarea id="description" name="description" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" placeholder="Provide details about what is included with this ticket type...">{{ old('description') }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('description')" />
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                        <a href="{{ route('organizer.events.tickets.index', $event) }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                            Cancel
                        </a>
                        <x-primary-button>
                            {{ __('Save Ticket Type') }}
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
