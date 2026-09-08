<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>500 — Server Error | {{ config('app.name', 'EventPulse') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-900 antialiased bg-slate-50 min-h-screen flex items-center justify-center p-4">
        <div class="max-w-md w-full text-center py-12 px-6 bg-white rounded-2xl shadow-sm border border-slate-100">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-rose-50 text-rose-600 mb-6">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <p class="text-xs font-semibold tracking-wider text-rose-600 uppercase mb-2">Error 500 &bull; Unexpected Error</p>
            <h1 class="text-2xl font-bold text-slate-900 mb-3">Internal Server Error</h1>
            <p class="text-sm text-slate-600 mb-8 leading-relaxed">
                An unexpected condition occurred while processing this request. Our technical staff has been notified.
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                <button onclick="window.location.reload()" class="w-full sm:w-auto px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-xl transition">
                    Retry
                </button>
                <a href="{{ route('home') }}" class="w-full sm:w-auto px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                    Return to Safety
                </a>
            </div>
        </div>
    </body>
</html>
