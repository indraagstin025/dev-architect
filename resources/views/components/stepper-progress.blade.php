@props([
    'progress' => 1, // 1, 2, 3, or 4
    'variant' => 'full', // 'full' or 'compact'
    'needsReinjection' => false,
])

@php
    $steps = [
        1 => ['title' => 'Ide & Dokumen', 'desc' => 'Konsep & Fitur'],
        2 => ['title' => 'Dokumen ERD', 'desc' => 'Skema Database'],
        3 => ['title' => 'Terpasang', 'desc' => 'Folder Komputer'],
        4 => ['title' => 'Selesai', 'desc' => 'Siap Koding'],
    ];
@endphp

@if($variant === 'full')
    <div class="w-full pt-1 pb-2">
        <div class="flex items-center justify-between relative">
            <!-- Background connecting line -->
            <div class="absolute left-4 right-4 top-3.5 -translate-y-1/2 h-0.5 bg-zinc-200 dark:bg-zinc-800 -z-0"></div>
            
            <!-- Active progress line fill -->
            @php
                $percentage = match($progress) {
                    1 => '0%',
                    2 => '33.33%',
                    3 => '66.66%',
                    4 => '100%',
                    default => '0%'
                };
            @endphp
            <div class="absolute left-4 top-3.5 -translate-y-1/2 h-0.5 bg-[#3ECF8E] transition-all duration-300 -z-0"
                 style="width: calc({{ $percentage }} * (100% - 2rem) / 100);"></div>

            @foreach($steps as $stepNum => $step)
                @php
                    $isCompleted = $progress > $stepNum || ($progress === 4 && $stepNum === 4);
                    $isCurrent = $progress === $stepNum && !($progress === 4 && $stepNum === 4);
                    $isPending = $progress < $stepNum;
                @endphp
                <div class="relative z-10 flex flex-col items-center group">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all shadow-xs
                        @if($isCompleted)
                            bg-[#3ECF8E] text-zinc-950 ring-2 ring-[#3ECF8E]/20
                        @elseif($isCurrent)
                            @if($needsReinjection && $stepNum === 3)
                                bg-amber-500 text-zinc-950 ring-4 ring-amber-500/20 animate-pulse
                            @else
                                bg-zinc-900 dark:bg-white text-white dark:text-zinc-950 ring-4 ring-emerald-500/20
                            @endif
                        @else
                            bg-zinc-100 dark:bg-zinc-800 text-zinc-400 dark:text-zinc-500 border border-zinc-200 dark:border-zinc-700
                        @endif">
                        @if($isCompleted && !($isCurrent && $needsReinjection))
                            <svg class="w-3.5 h-3.5 stroke-[3]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                            </svg>
                        @else
                            <span>{{ $stepNum }}</span>
                        @endif
                    </div>
                    <div class="text-center mt-1.5 hidden sm:block">
                        <span class="text-[11px] font-semibold block transition-colors
                            @if($isCompleted || $isCurrent)
                                text-zinc-900 dark:text-white
                            @else
                                text-zinc-400 dark:text-zinc-600
                            @endif">
                            {{ $step['title'] }}
                        </span>
                        <span class="text-[10px] text-zinc-400 dark:text-zinc-500 block">
                            @if($needsReinjection && $stepNum === 3)
                                <span class="text-amber-500 font-medium">Revisi Skema</span>
                            @else
                                {{ $step['desc'] }}
                            @endif
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@else
    <!-- Compact Variant (for Cards & Lists) -->
    <div class="inline-flex items-center gap-2">
        <div class="flex items-center gap-1">
            @for($i = 1; $i <= 4; $i++)
                <span class="w-2.5 h-1 rounded-full transition-all
                    @if($progress >= $i)
                        @if($needsReinjection && $i === 3)
                            bg-amber-500
                        @else
                            bg-[#3ECF8E]
                        @endif
                    @else
                        bg-zinc-200 dark:bg-zinc-800
                    @endif"></span>
            @endfor
        </div>
        <span class="text-[11px] font-semibold text-zinc-600 dark:text-zinc-400">
            @if($needsReinjection)
                <span class="text-amber-500 font-bold">● Perlu Injeksi Ulang</span>
            @else
                <span>{{ $progress }}/4 {{ $steps[$progress]['title'] }}</span>
            @endif
        </span>
    </div>
@endif
