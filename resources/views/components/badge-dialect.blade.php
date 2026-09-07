@props([
    'dialect' => 'mysql'
])

@php
    $dl = is_string($dialect) ? strtolower($dialect) : strtolower($dialect?->value ?? 'mysql');
    $label = match($dl) {
        'mysql' => 'MySQL',
        'pgsql', 'postgres', 'postgresql' => 'PostgreSQL',
        'sqlite' => 'SQLite',
        'sqlsrv', 'mssql' => 'SQL Server',
        default => strtoupper($dl),
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-medium bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 text-zinc-700 dark:text-zinc-300 tracking-tight shadow-xs transition-colors']) }}>
    <x-icon-database :dialect="$dl" class="w-3 h-3 text-zinc-400" />
    <span>{{ $label }}</span>
</span>
