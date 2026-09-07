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

@if($ed === 'terminal')
    <svg {{ $attributes->merge(['class' => 'w-3.5 h-3.5 shrink-0 inline-block text-current']) }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 17l6-6-6-6m8 14h8"></path>
    </svg>
@else
    <img src="{{ asset('assets/icons/' . $iconFile) }}" 
         alt="{{ $editor }}" 
         loading="lazy"
         {{ $attributes->merge(['class' => 'w-3.5 h-3.5 shrink-0 object-contain inline-block']) }} />
@endif
