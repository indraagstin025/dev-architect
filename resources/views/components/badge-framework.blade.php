@props([
    'framework' => 'laravel'
])

@php
    $fw = is_string($framework) ? strtolower($framework) : strtolower($framework?->value ?? 'laravel');
    $label = match($fw) {
        'laravel' => 'Laravel',
        'express_prisma' => 'Express + Prisma',
        'express_drizzle' => 'Express + Drizzle',
        'springboot_hibernate' => 'Spring Boot',
        'raw_sql' => 'Raw SQL',
        default => strtoupper($fw),
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-medium bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 text-zinc-700 dark:text-zinc-300 tracking-tight shadow-xs transition-colors']) }}>
    <x-icon-framework :framework="$fw" class="w-3.5 h-3.5" />
    <span>{{ $label }}</span>
</span>
