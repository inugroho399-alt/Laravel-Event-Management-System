<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 mb-1">
            <a href="{{ route('admin.users.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition">
                &larr; {{ __('Back to Users') }}
            </a>
            <span class="text-gray-300">/</span>
            <span class="text-xs text-gray-500 font-medium">Edit User</span>
        </div>
        <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
            {{ __('Edit User:') }} {{ $user->name }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if ($errors->any())
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm space-y-1">
                    @foreach ($errors->all() as $error)
                        <div>&bull; {{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="bg-white p-6 sm:p-8 rounded-xl border border-gray-100 shadow-sm">
                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="name" :value="__('Full Name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus />
                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                    </div>

                    <div>
                        <x-input-label for="email" :value="__('Email Address')" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required />
                        <x-input-error class="mt-2" :messages="$errors->get('email')" />
                    </div>

                    <div>
                        <x-input-label for="role" :value="__('Platform Role')" />
                        <select id="role" name="role"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            @foreach ($roles as $role)
                                <option value="{{ $role->value }}" @selected(old('role', $user->role->value) === $role->value)>
                                    {{ $role->label() }}
                                    @if ($role === \App\Enums\UserRole::Admin)
                                        (Full Superuser Access)
                                    @elseif ($role === \App\Enums\UserRole::Organizer)
                                        (Can Host Events & Scan Check-ins)
                                    @else
                                        (Standard Attendee)
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('role')" />

                        @if ($user->id === auth()->id())
                            <p class="text-xs text-amber-600 mt-2">
                                &bull; Note: You are currently editing your own account. Self-demoting from Administrator is disabled.
                            </p>
                        @endif
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                        <a href="{{ route('admin.users.index') }}" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 uppercase tracking-wider hover:bg-gray-50 transition">
                            Cancel
                        </a>
                        <button type="submit"
                                class="inline-flex items-center px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold uppercase tracking-widest transition shadow-sm">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
