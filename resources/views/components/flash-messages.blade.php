@if (session('success') || session('status') || session('error') || session('warning'))
    <div
        x-data="{ show: true }"
        x-show="show"
        x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200 transform"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        x-init="setTimeout(() => show = false, 6000)"
        class="fixed top-5 right-5 z-50 max-w-md w-full px-4 sm:px-0 pointer-events-none"
    >
        @if (session('success') || session('status'))
            <div class="pointer-events-auto flex items-center justify-between gap-3 p-4 rounded-xl bg-white border border-emerald-100 shadow-xl shadow-emerald-500/10 text-slate-800">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="text-sm font-medium text-slate-800 truncate">
                        {{ session('success') ?? session('status') }}
                    </div>
                </div>
                <button @click="show = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        @elseif (session('error'))
            <div class="pointer-events-auto flex items-center justify-between gap-3 p-4 rounded-xl bg-white border border-rose-100 shadow-xl shadow-rose-500/10 text-slate-800">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="text-sm font-medium text-slate-800 truncate">
                        {{ session('error') }}
                    </div>
                </div>
                <button @click="show = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        @elseif (session('warning'))
            <div class="pointer-events-auto flex items-center justify-between gap-3 p-4 rounded-xl bg-white border border-amber-100 shadow-xl shadow-amber-500/10 text-slate-800">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="text-sm font-medium text-slate-800 truncate">
                        {{ session('warning') }}
                    </div>
                </div>
                <button @click="show = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        @endif
    </div>
@endif
