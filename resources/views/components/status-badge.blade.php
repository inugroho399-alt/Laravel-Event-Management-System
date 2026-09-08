@props([
    'status',
    'size' => 'md',
])

@php
    $normalized = strtolower($status instanceof \BackedEnum ? $status->value : (string) $status);

    $colorClasses = match ($normalized) {
        'published', 'confirmed', 'attended', 'active' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20',
        'ongoing' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20',
        'completed' => 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-700/10',
        'draft', 'pending' => 'bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-600/20',
        'cancelled', 'rejected' => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20',
        'admin' => 'bg-purple-50 text-purple-700 ring-1 ring-inset ring-purple-600/20',
        'organizer' => 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-600/20',
        'participant' => 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-600/20',
        default => 'bg-gray-100 text-gray-700 ring-1 ring-inset ring-gray-600/20',
    };

    $dotColor = match ($normalized) {
        'published', 'confirmed', 'attended', 'active' => 'bg-emerald-500',
        'ongoing' => 'bg-amber-500 animate-pulse',
        'completed' => 'bg-blue-500',
        'draft', 'pending' => 'bg-slate-400',
        'cancelled', 'rejected' => 'bg-rose-500',
        'admin' => 'bg-purple-500',
        'organizer' => 'bg-indigo-500',
        'participant' => 'bg-sky-500',
        default => 'bg-gray-400',
    };

    $sizeClasses = match ($size) {
        'sm' => 'px-2 py-0.5 text-xs gap-1',
        'lg' => 'px-3 py-1.5 text-sm gap-2',
        default => 'px-2.5 py-1 text-xs gap-1.5',
    };

    $label = $status instanceof \App\Enums\EventStatus || $status instanceof \App\Enums\RegistrationStatus || $status instanceof \App\Enums\UserRole
        ? $status->label()
        : ucfirst($normalized);
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center font-medium rounded-full {$sizeClasses} {$colorClasses}"]) }}>
    <span class="h-1.5 w-1.5 rounded-full {{ $dotColor }}"></span>
    <span>{{ $label }}</span>
</span>
