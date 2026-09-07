@props([
    'editor' => 'vscode'
])

@php
    $ed = strtolower($editor);
    $iconMap = [
        'vscode' => 'vscode.svg',
        'explorer' => 'explorer.svg',
        'zed' => 'zed.svg',
        'antigravity' => 'antigravity.svg',
    ];
    $iconFile = $iconMap[$ed] ?? 'vscode.svg';
@endphp

<img src="{{ asset('assets/icons/' . $iconFile) }}" 
     alt="{{ $editor }}" 
     loading="lazy"
     {{ $attributes->merge(['class' => 'w-3.5 h-3.5 shrink-0 object-contain inline-block']) }} />
