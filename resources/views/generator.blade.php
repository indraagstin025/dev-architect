@extends('layouts.app')

@php
    $headerTitle = 'Pembuat Database';
    $headerSubtitle = 'Buat Struktur Database Otomatis';

    $activeProject = $activeProject ?? null;
    $projectFramework = $projectFramework ?? 'laravel';
    $projectDialect = $projectDialect ?? 'mysql';
    $isDialectLocked = $isDialectLocked ?? ($activeProject?->database_dialect !== null);
    $dialectLabel = $activeProject?->database_dialect?->label() ?? '';

    $allowedFrameworks = $allowedFrameworks ?? ($activeProject ? app(\App\Services\ProjectService::class)->allowedTargetFrameworks($activeProject) : []);
    $allowedTargets = array_map(fn ($f) => $f->value, $allowedFrameworks);
    $allowedLabels = array_map(fn ($f) => $f->label(), $allowedFrameworks);
    $projectLabel = $activeProject?->framework_type?->label() ?? '';

    $isLaravelAllowed = count($allowedTargets) === 0 || in_array('laravel', $allowedTargets);
    $isPrismaAllowed = count($allowedTargets) === 0 || in_array('express_prisma', $allowedTargets);
    $isDrizzleAllowed = count($allowedTargets) === 0 || in_array('express_drizzle', $allowedTargets);
    $isSpringAllowed = count($allowedTargets) === 0 || in_array('springboot_hibernate', $allowedTargets);
    $isRawSqlAllowed = count($allowedTargets) === 0 || in_array('raw_sql', $allowedTargets);
@endphp

@section('content')
<div class="max-w-6xl mx-auto space-y-6">

    @if(!$activeProject)
        <!-- Empty State When No Project is Registered -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-12 text-center space-y-3.5 shadow-xs transition-colors">
            <div class="w-12 h-12 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center mx-auto text-zinc-400">
                <svg class="w-6 h-6 text-zinc-400 dark:text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
            </div>
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Belum Ada Proyek yang Dipilih</h3>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 max-w-sm mx-auto">Pilih atau daftarkan proyek terlebih dahulu di menu Daftar Proyek sebelum membuat skema database.</p>
            <a href="{{ url('/dashboard') }}" class="inline-flex px-4 py-2 bg-black text-white hover:bg-zinc-800 dark:bg-white dark:text-black dark:hover:bg-zinc-200 font-semibold rounded-lg text-xs transition-colors shadow-xs">
                Ke Daftar Proyek
            </a>
        </div>
    @else
        <!-- Top Banner: Active Project (Matching Mockup) -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex items-center justify-between transition-colors">
            <div class="flex items-center gap-3.5 min-w-0">
                <div class="w-12 h-12 rounded-lg border border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-950 flex items-center justify-center p-2 shrink-0">
                    <x-icon-framework :framework="$activeProject->framework_type" class="w-7 h-7 object-contain" />
                </div>
                <div class="leading-tight truncate">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-sm text-zinc-900 dark:text-white truncate">{{ $activeProject->project_name }}</span>
                        <span class="px-2.5 py-0.5 rounded-md text-xs font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700">
                            {{ $activeProject->framework_type === 'laravel' ? 'Laravel' : ($activeProject->framework_type === 'express_prisma' ? 'Express + Prisma' : ($activeProject->framework_type === 'express_drizzle' ? 'Express + Drizzle' : ucfirst($activeProject->framework_type?->value ?? (string)$activeProject->framework_type))) }}
                        </span>
                    </div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400 font-mono truncate mt-1" title="{{ $activeProject->absolute_path }}">
                        {{ $activeProject->absolute_path }}
                    </div>
                </div>
            </div>

            <button type="button" onclick="openEditor('{{ $activeProject->id }}', 'explorer')"
                class="px-3.5 py-2 bg-white dark:bg-zinc-950 hover:bg-zinc-50 dark:hover:bg-zinc-900 text-zinc-800 dark:text-zinc-200 rounded-lg text-xs font-medium border border-zinc-200 dark:border-zinc-800 transition-colors flex items-center gap-2 shadow-xs shrink-0"
                title="Buka di File Explorer">
                <svg class="w-4 h-4 text-zinc-600 dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                </svg>
                <span>Buka di Folder</span>
            </button>
        </div>

        <form id="generator-form" onsubmit="handleGenerate(event)" class="space-y-6">
            
            <!-- Hidden inputs for backend submission -->
            <input type="hidden" id="target_framework" name="target_framework" value="{{ $projectFramework }}">
            <input type="hidden" id="database_dialect" name="database_dialect" value="{{ $projectDialect }}">

            <!-- 01 TARGET SECTION -->
            <div class="space-y-3">
                <div class="flex items-center gap-2 text-xs font-bold">
                    <span class="text-emerald-600 dark:text-emerald-400 font-mono">01</span>
                    <span class="text-zinc-700 dark:text-zinc-300 uppercase tracking-wider">PILIH TEKNOLOGI PROYEK</span>
                </div>

                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs space-y-6 transition-colors">
                    
                    <!-- Row 1: Framework / ORM & Target Version -->
                    <div class="flex flex-col lg:flex-row lg:items-start gap-4 justify-between">
                        
                        <!-- Left: Framework Cards -->
                        <div class="flex-1 space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300">Framework / ORM</label>
                                @if($activeProject && count($allowedTargets) > 0)
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-medium bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                        <span>🔒</span>
                                        <span>Terkunci Otomatis: <strong>{{ $projectLabel }}</strong></span>
                                    </span>
                                @endif
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3" id="framework-pills">
                                
                                <!-- Laravel -->
                                <button type="button" 
                                    @if($isLaravelAllowed) onclick="selectFramework('laravel', 'Laravel (Eloquent ORM)')" @endif 
                                    data-val="laravel"
                                    @if(!$isLaravelAllowed) disabled title="Terkunci: Tidak kompatibel dengan proyek aktif ({{ $projectLabel }})" @endif
                                    class="fw-pill p-3.5 rounded-lg border border-zinc-200 dark:border-zinc-800 transition-all text-left flex items-center justify-between gap-2.5 shadow-xs {{ !$isLaravelAllowed ? 'opacity-35 cursor-not-allowed bg-zinc-100/60 dark:bg-zinc-900/40 select-none' : 'bg-white dark:bg-zinc-950 group cursor-pointer' }}">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <img src="{{ asset('assets/icons/Laravel.svg') }}" class="w-6 h-6 object-contain shrink-0" alt="Laravel">
                                        <div class="leading-tight truncate">
                                            <div class="text-xs font-bold text-zinc-900 dark:text-white truncate">Laravel</div>
                                            <div class="text-[10px] text-zinc-400 mt-0.5 truncate">Eloquent ORM</div>
                                        </div>
                                    </div>
                                    @if($isLaravelAllowed)
                                        <span class="w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0 text-[10px] font-bold fw-check opacity-0 transition-opacity">✔</span>
                                    @else
                                        <span class="text-[10px] font-mono text-zinc-400 dark:text-zinc-500 shrink-0" title="Terkunci">🔒</span>
                                    @endif
                                </button>

                                <!-- Express + Prisma -->
                                <button type="button" 
                                    @if($isPrismaAllowed) onclick="selectFramework('express_prisma', 'Express (Prisma ORM)')" @endif 
                                    data-val="express_prisma"
                                    @if(!$isPrismaAllowed) disabled title="Terkunci: Tidak kompatibel dengan proyek aktif ({{ $projectLabel }})" @endif
                                    class="fw-pill p-3.5 rounded-lg border border-zinc-200 dark:border-zinc-800 transition-all text-left flex items-center justify-between gap-2.5 shadow-xs {{ !$isPrismaAllowed ? 'opacity-35 cursor-not-allowed bg-zinc-100/60 dark:bg-zinc-900/40 select-none' : 'bg-white dark:bg-zinc-950 group cursor-pointer' }}">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <img src="{{ asset('assets/icons/ExpressJS.svg') }}" class="w-6 h-6 object-contain shrink-0 dark:invert-0 invert" alt="Express">
                                        <div class="leading-tight truncate">
                                            <div class="text-xs font-bold text-zinc-900 dark:text-white truncate">Express + Prisma</div>
                                            <div class="text-[10px] text-zinc-400 mt-0.5 truncate">Schema & Client</div>
                                        </div>
                                    </div>
                                    @if($isPrismaAllowed)
                                        <span class="w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0 text-[10px] font-bold fw-check opacity-0 transition-opacity">✔</span>
                                    @else
                                        <span class="text-[10px] font-mono text-zinc-400 dark:text-zinc-500 shrink-0" title="Terkunci">🔒</span>
                                    @endif
                                </button>

                                <!-- Express + Drizzle -->
                                <button type="button" 
                                    @if($isDrizzleAllowed) onclick="selectFramework('express_drizzle', 'Express (Drizzle ORM)')" @endif 
                                    data-val="express_drizzle"
                                    @if(!$isDrizzleAllowed) disabled title="Terkunci: Tidak kompatibel dengan proyek aktif ({{ $projectLabel }})" @endif
                                    class="fw-pill p-3.5 rounded-lg border border-zinc-200 dark:border-zinc-800 transition-all text-left flex items-center justify-between gap-2.5 shadow-xs {{ !$isDrizzleAllowed ? 'opacity-35 cursor-not-allowed bg-zinc-100/60 dark:bg-zinc-900/40 select-none' : 'bg-white dark:bg-zinc-950 group cursor-pointer' }}">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <img src="{{ asset('assets/icons/ExpressJS.svg') }}" class="w-6 h-6 object-contain shrink-0 dark:invert-0 invert" alt="Express">
                                        <div class="leading-tight truncate">
                                            <div class="text-xs font-bold text-zinc-900 dark:text-white truncate">Express + Drizzle</div>
                                            <div class="text-[10px] text-zinc-400 mt-0.5 truncate">TypeScript ORM</div>
                                        </div>
                                    </div>
                                    @if($isDrizzleAllowed)
                                        <span class="w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0 text-[10px] font-bold fw-check opacity-0 transition-opacity">✔</span>
                                    @else
                                        <span class="text-[10px] font-mono text-zinc-400 dark:text-zinc-500 shrink-0" title="Terkunci">🔒</span>
                                    @endif
                                </button>

                                <!-- Spring Boot -->
                                <button type="button" 
                                    @if($isSpringAllowed) onclick="selectFramework('springboot_hibernate', 'Spring Boot (JPA / Hibernate)')" @endif 
                                    data-val="springboot_hibernate"
                                    @if(!$isSpringAllowed) disabled title="Terkunci: Tidak kompatibel dengan proyek aktif ({{ $projectLabel }})" @endif
                                    class="fw-pill p-3.5 rounded-lg border border-zinc-200 dark:border-zinc-800 transition-all text-left flex items-center justify-between gap-2.5 shadow-xs {{ !$isSpringAllowed ? 'opacity-35 cursor-not-allowed bg-zinc-100/60 dark:bg-zinc-900/40 select-none' : 'bg-white dark:bg-zinc-950 group cursor-pointer' }}">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <img src="{{ asset('assets/icons/Springboot.svg') }}" class="w-6 h-6 object-contain shrink-0" alt="Spring Boot">
                                        <div class="leading-tight truncate">
                                            <div class="text-xs font-bold text-zinc-900 dark:text-white truncate">Spring Boot</div>
                                            <div class="text-[10px] text-zinc-400 mt-0.5 truncate">JPA / Hibernate</div>
                                        </div>
                                    </div>
                                    @if($isSpringAllowed)
                                        <span class="w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0 text-[10px] font-bold fw-check opacity-0 transition-opacity">✔</span>
                                    @else
                                        <span class="text-[10px] font-mono text-zinc-400 dark:text-zinc-500 shrink-0" title="Terkunci">🔒</span>
                                    @endif
                                </button>

                                <!-- Raw SQL -->
                                <button type="button" 
                                    @if($isRawSqlAllowed) onclick="selectFramework('raw_sql', 'Raw SQL (Universal DDL)')" @endif 
                                    data-val="raw_sql"
                                    @if(!$isRawSqlAllowed) disabled title="Terkunci: Tidak kompatibel dengan proyek aktif ({{ $projectLabel }})" @endif
                                    class="fw-pill p-3.5 rounded-lg border border-zinc-200 dark:border-zinc-800 transition-all text-left flex items-center justify-between gap-2.5 shadow-xs {{ !$isRawSqlAllowed ? 'opacity-35 cursor-not-allowed bg-zinc-100/60 dark:bg-zinc-900/40 select-none' : 'bg-white dark:bg-zinc-950 group cursor-pointer' }}">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <img src="{{ asset('assets/icons/SQLite.svg') }}" class="w-6 h-6 object-contain shrink-0" alt="Raw SQL">
                                        <div class="leading-tight truncate">
                                            <div class="text-xs font-bold text-zinc-900 dark:text-white truncate">Raw SQL</div>
                                            <div class="text-[10px] text-zinc-400 mt-0.5 truncate">Universal DDL</div>
                                        </div>
                                    </div>
                                    @if($isRawSqlAllowed)
                                        <span class="w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0 text-[10px] font-bold fw-check opacity-0 transition-opacity">✔</span>
                                    @else
                                        <span class="text-[10px] font-mono text-zinc-400 dark:text-zinc-500 shrink-0" title="Terkunci">🔒</span>
                                    @endif
                                </button>

                            </div>
                        </div>

                        <!-- Right: Target Version Dropdown (Matching Screenshot) -->
                        <div class="w-full lg:w-36 shrink-0 space-y-2">
                            <label for="target_version" class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300">Versi Framework</label>
                            <div class="relative">
                                <select id="target_version" name="target_version" onchange="updateReviewVersion()"
                                    class="w-full appearance-none bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-lg px-3.5 py-3 text-xs font-medium text-zinc-900 dark:text-white pr-8 focus:outline-none focus:ring-1 focus:ring-emerald-500 shadow-xs cursor-pointer">
                                    <option value="13" selected>13</option>
                                    <option value="12">12</option>
                                    <option value="11">11</option>
                                    <option value="10">10</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2.5 text-zinc-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Row 2: Database Engine -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300">Jenis Database</label>
                            @if($isDialectLocked)
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-medium bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                    <span>🔒</span>
                                    <span>Dialek Proyek: <strong>{{ $dialectLabel }}</strong> (Terkunci Otomatis)</span>
                                </span>
                            @else
                                <span class="text-[11px] text-zinc-400 dark:text-zinc-500">
                                    Belum terdeteksi di proyek • Bebas pilih target
                                </span>
                            @endif
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3" id="dialect-pills">
                            @php
                                $dialects = [
                                    'mysql' => ['label' => 'MySQL', 'desc' => 'MariaDB • 8.0+', 'icon' => 'MySQL.svg', 'fullLabel' => 'MySQL (8.0+)'],
                                    'pgsql' => ['label' => 'PostgreSQL', 'desc' => 'PostgreSQL • 15+', 'icon' => 'PostgreSQL.svg', 'fullLabel' => 'PostgreSQL (15+)'],
                                    'sqlite' => ['label' => 'SQLite', 'desc' => 'Embedded file', 'icon' => 'SQLite.svg', 'fullLabel' => 'SQLite (Embedded)'],
                                    'sqlsrv' => ['label' => 'SQL Server', 'desc' => 'Microsoft T-SQL', 'icon' => 'SQLServer.svg', 'fullLabel' => 'SQL Server (T-SQL)'],
                                ];
                            @endphp

                            @foreach($dialects as $dKey => $dInfo)
                                @php
                                    $isLockedOut = $isDialectLocked && ($projectDialect !== $dKey);
                                @endphp
                                <button type="button" 
                                    @if(!$isLockedOut) onclick="selectDialect('{{ $dKey }}', '{{ $dInfo['fullLabel'] }}')" @endif 
                                    data-val="{{ $dKey }}"
                                    @if($isLockedOut) disabled title="Terkunci: Proyek ini dikonfigurasi untuk {{ $dialectLabel }}" @endif
                                    class="dl-pill p-3.5 rounded-lg border border-zinc-200 dark:border-zinc-800 transition-all flex items-center justify-between gap-3 text-left shadow-xs {{ $isLockedOut ? 'opacity-35 cursor-not-allowed bg-zinc-100/60 dark:bg-zinc-900/40 select-none' : 'bg-white dark:bg-zinc-950 group cursor-pointer' }}">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <img src="{{ asset('assets/icons/' . $dInfo['icon']) }}" class="w-7 h-7 object-contain shrink-0" alt="{{ $dInfo['label'] }}">
                                        <div class="leading-tight truncate">
                                            <div class="text-xs font-bold text-zinc-900 dark:text-white truncate">{{ $dInfo['label'] }}</div>
                                            <div class="text-[10px] text-zinc-400 mt-0.5 truncate">{{ $dInfo['desc'] }}</div>
                                        </div>
                                    </div>
                                    @if(!$isLockedOut)
                                        <span class="w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0 text-[10px] font-bold dl-check opacity-0 transition-opacity">✔</span>
                                    @else
                                        <span class="text-[10px] font-mono text-zinc-400 dark:text-zinc-500 shrink-0" title="Terkunci">🔒</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>

                </div>
            </div>

            <!-- Hard Block Banner (Opsi B: mismatch selalu ditolak backend 422) -->
            <div id="compat-warning" class="hidden bg-red-50 dark:bg-red-950/60 border border-red-200 dark:border-red-500/30 rounded-lg p-4 text-xs text-red-900 dark:text-red-200 space-y-1.5">
                <p id="compat-message" class="font-semibold"></p>
                <p class="text-red-700 dark:text-red-300">Pilih salah satu target yang kompatibel di atas untuk melanjutkan.</p>
            </div>

            <!-- 02 REQUIREMENTS & 03 GENERATE GRID (Matching Screenshot) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                
                <!-- Col 1: 02 REQUIREMENTS (Left 2 Cols) -->
                <div class="lg:col-span-2 space-y-3">
                    <div class="flex items-center gap-2 text-xs font-bold">
                        <span class="text-emerald-600 dark:text-emerald-400 font-mono">02</span>
                        <span class="text-zinc-700 dark:text-zinc-300 uppercase tracking-wider">JELASKAN KEBUTUHAN ANDA</span>
                    </div>

                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs space-y-4 transition-colors">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-sm font-bold text-zinc-900 dark:text-white">Ceritakan kebutuhan database atau aplikasi Anda</h3>
                                <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-0.5">Tuliskan fitur atau data apa saja yang ingin disimpan. Semakin jelas ceritanya, semakin pas database yang dibuatkan.</p>
                            </div>
                            <button type="button" onclick="insertSamplePrompt()"
                                class="px-3 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-800 text-xs font-medium text-zinc-700 dark:text-zinc-300 bg-white dark:bg-zinc-950 hover:bg-zinc-50 dark:hover:bg-zinc-800 shadow-xs transition-colors flex items-center gap-1.5 shrink-0">
                                <span>💡</span>
                                <span>Lihat Contoh Kebutuhan</span>
                            </button>
                        </div>

                        <textarea id="prompt_text" name="prompt_text" rows="5" required minlength="10" maxlength="5000"
                            placeholder="Contoh: Buatkan schema toko online dengan tabel users (nama, email unik), products (nama, harga, stok), orders (relasi ke users, total, status), dan order_items (relasi ke orders dan products, qty, harga satuan). Tambahkan index pada kolom yang sering dicari."
                            class="w-full bg-zinc-50/50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-lg p-4 text-xs font-sans text-zinc-900 dark:text-white placeholder:text-zinc-400 dark:placeholder:text-zinc-600 focus:outline-none focus:ring-1 focus:ring-emerald-500 transition-colors leading-relaxed"></textarea>

                        <div class="flex items-center justify-between pt-1">
                            <div class="text-xs text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                                <span>✦</span>
                                <span>AI akan otomatis membuatkan: Tabel data • Relasi data • Kunci pencarian • Skrip siap pakai</span>
                            </div>
                            <div class="text-xs text-zinc-400">
                                <span id="prompt-counter" class="font-mono font-medium">0</span> / 5000 karakter
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Col 2: 03 GENERATE (Right 1 Col) -->
                <div class="space-y-3">
                    <div class="flex items-center gap-2 text-xs font-bold">
                        <span class="text-emerald-600 dark:text-emerald-400 font-mono">03</span>
                        <span class="text-zinc-700 dark:text-zinc-300 uppercase tracking-wider">TINJAU & BUAT</span>
                    </div>

                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs flex flex-col justify-between space-y-6 transition-colors">
                        <div class="space-y-4">
                            <h3 class="text-xs font-bold text-zinc-900 dark:text-white">Ringkasan pilihan Anda</h3>
                            
                            <div class="space-y-3">
                                <!-- Check 1: Framework -->
                                <div class="flex items-start gap-2.5">
                                    <span class="w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0 text-[10px] font-bold mt-0.5">✔</span>
                                    <div class="leading-tight">
                                        <div class="text-[11px] text-zinc-400">Framework</div>
                                        <div id="review-framework" class="text-xs font-bold text-zinc-900 dark:text-white mt-0.5">Laravel (Eloquent ORM)</div>
                                    </div>
                                </div>

                                <!-- Check 2: Database Engine -->
                                <div class="flex items-start gap-2.5">
                                    <span class="w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0 text-[10px] font-bold mt-0.5">✔</span>
                                    <div class="leading-tight">
                                        <div class="text-[11px] text-zinc-400">Jenis Database</div>
                                        <div id="review-dialect" class="text-xs font-bold text-zinc-900 dark:text-white mt-0.5">MySQL (8.0+)</div>
                                    </div>
                                </div>

                                <!-- Check 3: Target Version -->
                                <div class="flex items-start gap-2.5">
                                    <span class="w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0 text-[10px] font-bold mt-0.5">✔</span>
                                    <div class="leading-tight">
                                        <div class="text-[11px] text-zinc-400">Versi Framework</div>
                                        <div id="review-version" class="text-xs font-bold text-zinc-900 dark:text-white mt-0.5">13</div>
                                    </div>
                                </div>

                                <!-- Check 4: Requirements -->
                                <div class="flex items-start gap-2.5">
                                    <span id="review-req-check" class="w-4 h-4 rounded-full bg-zinc-200 dark:bg-zinc-800 text-zinc-400 flex items-center justify-center shrink-0 text-[10px] font-bold mt-0.5">✔</span>
                                    <div class="leading-tight">
                                        <div class="text-[11px] text-zinc-400">Kebutuhan Sistem</div>
                                        <div id="review-requirements" class="text-xs font-bold text-zinc-500 dark:text-zinc-400 mt-0.5">Belum diisi</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Generate Button (Matching Screenshot) -->
                        <button type="submit" id="btn-generate"
                            class="w-full py-3.5 bg-black hover:bg-zinc-800 text-white dark:bg-white dark:text-black dark:hover:bg-zinc-200 font-semibold rounded-lg text-xs flex items-center justify-center gap-2 shadow-xs transition-colors cursor-pointer focus:ring-2 focus:ring-emerald-500/40">
                            <svg class="w-4 h-4 text-emerald-400 dark:text-emerald-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/>
                            </svg>
                            <span id="btn-generate-label">Buat Database Sekarang</span>
                        </button>
                    </div>
                </div>

            </div>

            <!-- Bottom 3 Feature Highlights (Matching Screenshot) -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs grid grid-cols-1 md:grid-cols-3 gap-5 md:gap-0 md:divide-x divide-zinc-200 dark:divide-zinc-800 transition-colors">
                
                <!-- Feature 1 -->
                <div class="flex items-center gap-3.5 md:pr-6">
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-100 dark:border-emerald-800/80 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                        </svg>
                    </div>
                    <div class="leading-tight">
                        <h4 class="text-xs font-bold text-zinc-900 dark:text-white">Dibantu AI Cerdas</h4>
                        <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-0.5">AI memahami instruksi Anda dan merancang struktur data yang rapi serta efisien.</p>
                    </div>
                </div>

                <!-- Feature 2 -->
                <div class="flex items-center gap-3.5 md:px-6">
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-100 dark:border-emerald-800/80 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <div class="leading-tight">
                        <h4 class="text-xs font-bold text-zinc-900 dark:text-white">Standar Industri</h4>
                        <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-0.5">Mengikuti aturan penamaan tabel, relasi kunci, dan indexing yang aman.</p>
                    </div>
                </div>

                <!-- Feature 3 -->
                <div class="flex items-center gap-3.5 md:pl-6">
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-100 dark:border-emerald-800/80 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                        </svg>
                    </div>
                    <div class="leading-tight">
                        <h4 class="text-xs font-bold text-zinc-900 dark:text-white">Siap Digunakan</h4>
                        <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-0.5">Hasil dapat langsung disimpan ke proyek, diekspor ke SQL, atau dilihat dalam diagram (ERD).</p>
                    </div>
                </div>

            </div>

        </form>
    @endif

</div>
@endsection

@push('scripts')
<script>
const promptInput = document.getElementById('prompt_text');
if (promptInput) {
    promptInput.addEventListener('input', () => {
        const len = promptInput.value.length;
        document.getElementById('prompt-counter').textContent = len;
        
        // Update review box dynamically
        const reqEl = document.getElementById('review-requirements');
        const reqCheck = document.getElementById('review-req-check');
        if (len >= 10) {
            reqEl.textContent = 'Siap dirancang (' + len + ' karakter)';
            reqEl.className = 'text-xs font-bold text-emerald-600 dark:text-emerald-400 mt-0.5';
            reqCheck.className = 'w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0 text-[10px] font-bold mt-0.5';
        } else {
            reqEl.textContent = 'Belum diisi';
            reqEl.className = 'text-xs font-bold text-zinc-500 dark:text-zinc-400 mt-0.5';
            reqCheck.className = 'w-4 h-4 rounded-full bg-zinc-200 dark:bg-zinc-800 text-zinc-400 flex items-center justify-center shrink-0 text-[10px] font-bold mt-0.5';
        }
    });
}

function insertSamplePrompt() {
    if (!promptInput) return;
    promptInput.value = "Buatkan schema toko online lengkap dengan tabel users (nama, email unik, password, role), categories (nama, slug), products (kategori_id, nama, harga, stok, deskripsi), orders (user_id, nomor_invoice, total_harga, status, tanggal), dan order_items (order_id, product_id, jumlah, harga_satuan). Tambahkan index pada kolom foreign key dan status order.";
    promptInput.dispatchEvent(new Event('input'));
    promptInput.focus();
}

function updateReviewVersion() {
    const val = document.getElementById('target_version')?.value || '13';
    const rev = document.getElementById('review-version');
    if (rev) rev.textContent = val;
}

function selectFramework(val, label) {
    const input = document.getElementById('target_framework');
    if (input) input.value = val;

    document.querySelectorAll('.fw-pill').forEach(btn => {
        const isCurrent = btn.dataset.val === val;
        btn.classList.toggle('border-emerald-500', isCurrent);
        btn.classList.toggle('dark:border-emerald-500', isCurrent);
        btn.classList.toggle('ring-1', isCurrent);
        btn.classList.toggle('ring-emerald-500', isCurrent);

        btn.classList.toggle('border-zinc-200', !isCurrent);
        btn.classList.toggle('dark:border-zinc-800', !isCurrent);
        btn.classList.remove('ring-0');

        const check = btn.querySelector('.fw-check');
        if (check) {
            check.classList.toggle('opacity-100', isCurrent);
            check.classList.toggle('opacity-0', !isCurrent);
        }
    });

    const rev = document.getElementById('review-framework');
    if (rev && label) rev.textContent = label;

    checkCompatibility();
}

const isDialectLocked = @json($isDialectLocked);
const projectDialect = @json($projectDialect);

function selectDialect(val, label) {
    if (isDialectLocked && val !== projectDialect) {
        return;
    }

    const input = document.getElementById('database_dialect');
    if (input) input.value = val;

    document.querySelectorAll('.dl-pill').forEach(btn => {
        const isCurrent = btn.dataset.val === val;
        btn.classList.toggle('border-emerald-500', isCurrent);
        btn.classList.toggle('dark:border-emerald-500', isCurrent);
        btn.classList.toggle('ring-1', isCurrent);
        btn.classList.toggle('ring-emerald-500', isCurrent);

        btn.classList.toggle('border-zinc-200', !isCurrent);
        btn.classList.toggle('dark:border-zinc-800', !isCurrent);

        const check = btn.querySelector('.dl-check');
        if (check) {
            check.classList.toggle('opacity-100', isCurrent);
            check.classList.toggle('opacity-0', !isCurrent);
        }
    });

    const rev = document.getElementById('review-dialect');
    if (rev && label) rev.textContent = label;
}

function checkCompatibility() {
    const target = document.getElementById('target_framework')?.value;
    const allowed = @json($allowedTargets);
    const allowedLabels = @json($allowedLabels);
    const warn = document.getElementById('compat-warning');
    if (!warn) return;

    const ok = allowed.length === 0 || allowed.includes(target);
    warn.classList.toggle('hidden', ok);
    if (!ok) {
        const label = document.querySelector('#framework-pills .fw-pill[data-val="' + target + '"] .text-xs.font-bold')?.textContent?.trim() || target;
        document.getElementById('compat-message').textContent =
            `Target [${label}] DITOLAK untuk proyek ini ({{ $projectLabel }}). Didukung: ${allowedLabels.join(' • ')}`;
    }
    const btn = document.getElementById('btn-generate');
    if (btn) btn.disabled = !ok;
}

// Initial visual state
const FW_LABELS = {laravel:'Laravel (Eloquent ORM)', express_prisma:'Express (Prisma ORM)', express_drizzle:'Express (Drizzle ORM)', springboot_hibernate:'Spring Boot (JPA / Hibernate)', raw_sql:'Raw SQL (Universal DDL)'};
const DL_LABELS = {mysql:'MySQL (8.0+)', pgsql:'PostgreSQL (15+)', sqlite:'SQLite (Embedded)', sqlsrv:'SQL Server (T-SQL)'};
const initFw = document.getElementById('target_framework')?.value || 'laravel';
const initDl = document.getElementById('database_dialect')?.value || 'mysql';
selectFramework(initFw, FW_LABELS[initFw]);
selectDialect(initDl, DL_LABELS[initDl]);
updateReviewVersion();
checkCompatibility();

async function handleGenerate(e) {
    e.preventDefault();
    const target = document.getElementById('target_framework').value;
    const allowed = @json($allowedTargets);
    if (allowed.length > 0 && !allowed.includes(target)) {
        window.toast('Target tidak didukung untuk proyek ini — pilih target yang kompatibel.', 'error');
        return;
    }
    const btn = document.getElementById('btn-generate');
    const label = document.getElementById('btn-generate-label');
    btn.disabled = true;
    label.textContent = 'Memproses...';

    try {
        const res = await window.api('/api/generations/generate', {
            method: 'POST',
            body: {
                project_id: '{{ $activeProject ? $activeProject->id : '' }}',
                prompt_text: document.getElementById('prompt_text').value.trim(),
                target_framework: target,
                database_dialect: document.getElementById('database_dialect').value,
                target_version: document.getElementById('target_version').value.trim() || '13',
            }
        });
        window.toast('Masuk antrean, membuka halaman review...', 'info');
        window.location.href = `/generations/${res.data.id}`;
    } catch (err) {
        btn.disabled = false;
        label.textContent = 'Rancang Arsitektur';
    }
}
</script>
@endpush
