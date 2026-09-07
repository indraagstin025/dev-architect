@props([
    'dialect' => 'mysql'
])

@php
    $dl = strtolower($dialect);
    
    if (str_contains($dl, 'pg') || str_contains($dl, 'postgres')) {
        $iconFile = 'PostgreSQL.svg';
    } elseif (str_contains($dl, 'mysql') || str_contains($dl, 'maria')) {
        $iconFile = 'MySQL.svg';
    } elseif (str_contains($dl, 'sqlite')) {
        $iconFile = 'SQLite.svg';
    } elseif (str_contains($dl, 'sqlserver') || str_contains($dl, 'sqlsrv') || str_contains($dl, 'mssql')) {
        $iconFile = 'SQLServer.svg';
    } else {
        $iconFile = 'MySQL.svg';
    }
@endphp

<img src="{{ asset('assets/icons/' . $iconFile) }}" 
     alt="{{ $dl }}" 
     loading="lazy"
     {{ $attributes->merge(['class' => 'w-3.5 h-3.5 shrink-0 object-contain inline-block']) }} />
