<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-900 antialiased bg-slate-50">
        <div class="min-h-screen flex flex-col sm:justify-center items-center px-4 py-8 sm:py-12">
            <div class="mb-6">
                <a href="{{ route('home') }}" class="focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded-xl p-1 inline-block">
                    <x-application-logo class="w-48 h-12 text-slate-800" />
                </a>
            </div>

            <div class="w-full sm:max-w-md p-6 sm:p-8 bg-white shadow-sm border border-slate-100 rounded-2xl">
                {{ $slot }}
            </div>

            <div class="mt-8 text-center text-xs text-slate-400">
                &copy; {{ date('Y') }} EventPulse Management System. All rights reserved.
            </div>
        </div>
    </body>
</html>
