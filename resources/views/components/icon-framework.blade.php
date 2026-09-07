@props([
    'framework' => 'laravel'
])

@php
    $fw = is_string($framework) ? strtolower($framework) : strtolower($framework?->value ?? 'laravel');
    $classes = $attributes->get('class', '');
    $hasSize = preg_match('/\b[wh]-\d+/', $classes);
    $sizeClasses = $hasSize ? '' : 'w-3.5 h-3.5';
@endphp

@if(str_contains($fw, 'express') || str_contains($fw, 'node') || $fw === 'express_prisma' || $fw === 'express_drizzle')
    <img src="{{ asset('assets/icons/ExpressJS.svg') }}" alt="Express" loading="lazy" {{ $attributes->merge(['class' => $sizeClasses . ' shrink-0 object-contain inline-block dark:invert-0 invert']) }} />
@elseif(str_contains($fw, 'spring') || str_contains($fw, 'hibernate'))
    <img src="{{ asset('assets/icons/Springboot.svg') }}" alt="Spring Boot" loading="lazy" {{ $attributes->merge(['class' => $sizeClasses . ' shrink-0 object-contain inline-block']) }} />
@elseif($fw === 'raw_sql')
    <img src="{{ asset('assets/icons/SQLite.svg') }}" alt="SQL" loading="lazy" {{ $attributes->merge(['class' => $sizeClasses . ' shrink-0 object-contain inline-block']) }} />
@else
    <img src="{{ asset('assets/icons/Laravel.svg') }}" alt="Laravel" loading="lazy" {{ $attributes->merge(['class' => $sizeClasses . ' shrink-0 object-contain inline-block']) }} />
@endif
