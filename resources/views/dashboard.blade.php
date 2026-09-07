@extends('layouts.app')

@php
    $headerTitle = 'Dashboard';
    $headerSubtitle = 'Kelola Project & Dokumentasi';

    $projects = app(\App\Services\ProjectService::class)->listProjects();
    $activeProjectId = \App\Models\AppSetting::get('active_project_id');
    $activeProject = $activeProjectId ? $projects->firstWhere('id', $activeProjectId) : $projects->first();
    $docTotal = \App\Models\DocProject::count();
    $docActive = \App\Models\DocProject::where('status', 'active')->where('stage', '!=', 'done')->count();
@endphp

@section('content')
    <div class="w-full max-w-6xl mx-auto space-y-7">

        <!-- 1. HEADER DASHBOARD: Title, Subtitle & Primary Action -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-1">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white tracking-tight">Dashboard</h1>
                <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">Kelola project dan dokumentasi aplikasi Anda dalam satu tempat.</p>
            </div>
            <div class="flex items-center gap-2.5 shrink-0">
                <button type="button" onclick="openAddModal()" id="btn-header-add-project"
                    class="px-4 py-2 bg-[#3ECF8E] hover:bg-[#34b27b] text-zinc-950 font-semibold rounded-xl text-xs transition-colors shadow-xs flex items-center gap-2 cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <span>+ Tambah Project</span>
                </button>
            </div>
        </div>

        <!-- 2. HERO SECTION: Project yang Sedang Dikerjakan -->
        @if($activeProject)
            @php
                $activeFw = is_string($activeProject->framework_type) ? $activeProject->framework_type : ($activeProject->framework_type?->value ?? 'laravel');
                $activeLabel = match($activeFw) {
                    'laravel' => 'Laravel',
                    'express_prisma' => 'Express + Prisma',
                    'express_drizzle' => 'Express + Drizzle',
                    'springboot_hibernate' => 'Spring Boot',
                    'raw_sql' => 'Raw SQL',
                    default => ucfirst((string)$activeFw),
                };
                $activeDb = $activeProject->database_dialect?->label()
                    ?? ($activeProject->latestGeneration?->database_dialect?->label() 
                        ?? match($activeFw) {
                            'laravel' => 'SQLite',
                            'express_prisma' => 'PostgreSQL',
                            'express_drizzle' => 'PostgreSQL',
                            'springboot_hibernate' => 'PostgreSQL',
                            default => 'MySQL'
                        });
                $nextAction = $activeProject->next_action;
                $isDraft = $activeProject->isDraft();
            @endphp

            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-[#0c0c0e] p-5 sm:p-6 shadow-xs space-y-4 transition-all">
                <!-- Top Status Bar & Secondary Actions -->
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-2">
                        <div class="inline-flex items-center gap-2 text-xs font-semibold text-zinc-900 dark:text-white">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                            <span>Sedang Dikerjakan</span>
                        </div>
                        <span class="text-xs text-zinc-400 dark:text-zinc-500 hidden sm:inline">• Terakhir diubah {{ $activeProject->updated_at->diffForHumans() }}</span>
                    </div>

                    <!-- Secondary Actions: VS Code, Terminal, Folder, More Options -->
                    <div class="flex items-center gap-2">
                        @if(!$isDraft)
                            <button type="button" onclick="openEditor('{{ $activeProject->id }}', 'vscode')"
                                class="px-3 py-1.5 bg-zinc-50 hover:bg-zinc-100 dark:bg-zinc-900 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300 rounded-lg text-xs font-medium border border-zinc-200 dark:border-zinc-800 transition-colors flex items-center gap-1.5 shadow-2xs cursor-pointer"
                                title="Buka di VS Code">
                                <x-icon-editor editor="vscode" />
                                <span>VS Code</span>
                            </button>
                            <button type="button" onclick="openEditor('{{ $activeProject->id }}', 'terminal')"
                                class="px-3 py-1.5 bg-zinc-50 hover:bg-zinc-100 dark:bg-zinc-900 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300 rounded-lg text-xs font-medium border border-zinc-200 dark:border-zinc-800 transition-colors flex items-center gap-1.5 shadow-2xs cursor-pointer"
                                title="Buka Terminal Windows">
                                <svg class="w-3.5 h-3.5 text-zinc-500 dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <span>Terminal</span>
                            </button>
                            <button type="button" onclick="openEditor('{{ $activeProject->id }}', 'explorer')"
                                class="px-3 py-1.5 bg-zinc-50 hover:bg-zinc-100 dark:bg-zinc-900 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300 rounded-lg text-xs font-medium border border-zinc-200 dark:border-zinc-800 transition-colors flex items-center gap-1.5 shadow-2xs cursor-pointer"
                                title="Buka Folder di File Explorer">
                                <svg class="w-3.5 h-3.5 text-zinc-500 dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                                </svg>
                                <span>Buka Folder</span>
                            </button>
                        @else
                            <span class="text-xs text-amber-600 dark:text-amber-400 font-medium px-2 py-1 bg-amber-500/10 rounded-lg">
                                💡 Instal proyek untuk membuka di editor
                            </span>
                        @endif
                        
                        <!-- Popover Titik 3 -->
                        <div class="relative card-popover-wrapper">
                            <button type="button" onclick="toggleCardMenu('hero-more-menu', event)"
                                class="p-1.5 text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors cursor-pointer"
                                title="Opsi Lainnya">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                                </svg>
                            </button>
                            <div id="hero-more-menu"
                                class="hidden card-popover absolute right-0 top-full mt-1.5 w-48 rounded-xl bg-white dark:bg-[#121215] border border-zinc-200 dark:border-zinc-800 shadow-xl z-30 py-1 text-xs divide-y divide-zinc-100 dark:divide-zinc-800">
                                <div class="py-1">
                                    @if(!$isDraft)
                                        <button type="button" onclick="copyToClipboard('{{ addslashes($activeProject->absolute_path) }}')"
                                            class="w-full text-left px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300 flex items-center gap-2 transition-colors cursor-pointer">
                                            <svg class="w-3.5 h-3.5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                            <span>Salin Path Folder</span>
                                        </button>
                                        <button type="button" onclick="openEditor('{{ $activeProject->id }}', 'zed')"
                                            class="w-full text-left px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300 flex items-center gap-2 transition-colors cursor-pointer">
                                            <x-icon-editor editor="zed" />
                                            <span>Zed Editor</span>
                                        </button>
                                        <button type="button" onclick="openEditor('{{ $activeProject->id }}', 'antigravity')"
                                            class="w-full text-left px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300 flex items-center gap-2 transition-colors cursor-pointer">
                                            <x-icon-editor editor="antigravity" />
                                            <span>Antigravity IDE</span>
                                        </button>
                                    @endif
                                    @if($activeProject->doc_project_id)
                                        <a href="{{ url('/assistant') }}"
                                            class="w-full text-left px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300 flex items-center gap-2 transition-colors cursor-pointer">
                                            <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                                            <span>Buka Dokumen di Asisten AI</span>
                                        </a>
                                    @endif
                                </div>
                                <div class="py-1">
                                    <button type="button" onclick="confirmDeleteProject('{{ $activeProject->id }}', '{{ addslashes($activeProject->project_name) }}')"
                                        class="w-full text-left px-3 py-1.5 hover:bg-red-500/10 text-red-600 dark:text-red-400 flex items-center gap-2 transition-colors cursor-pointer">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        <span>Hapus Project</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stepper Progress 4 Tahap (TASK-M2-03) -->
                <x-stepper-progress :progress="$activeProject->lifecycle_progress" :needs-reinjection="$activeProject->needsReinjection()" variant="full" />

                <!-- Konten Utama: Identitas Project & Smart Decision Action Button -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-1">
                    <div class="flex items-start gap-3.5 min-w-0">
                        <div class="w-13 h-13 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-950 flex items-center justify-center p-2.5 shrink-0 shadow-2xs mt-0.5">
                            <x-icon-framework :framework="$activeProject->framework_type" class="w-8 h-8 object-contain" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 class="text-xl sm:text-2xl font-bold text-zinc-900 dark:text-white truncate tracking-tight" title="{{ $activeProject->project_name }}">
                                {{ $activeProject->project_name }}
                            </h2>
                            <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 font-medium">
                                <span>{{ $activeLabel }}</span>
                                <span class="text-zinc-300 dark:text-zinc-700">·</span>
                                <span>{{ $activeDb }}</span>
                            </div>
                            
                            <!-- Minimalist Path Chip / Draft Indicator -->
                            <div class="mt-2.5">
                                @if($isDraft)
                                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0 animate-pulse"></span>
                                        <span class="text-[11px] font-mono">📁 Draft (Belum Diinstal di Komputer)</span>
                                    </div>
                                @else
                                    <div class="relative group/path inline-flex items-center">
                                        <button type="button" 
                                            onclick="copyPathWithFeedback('{{ addslashes($activeProject->absolute_path) }}', this, event)"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono bg-zinc-50 hover:bg-zinc-100 dark:bg-zinc-900/60 dark:hover:bg-zinc-800 text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 border border-zinc-200/80 dark:border-zinc-800 transition-all cursor-pointer shadow-2xs"
                                            title="Klik untuk salin path folder">
                                            <svg class="w-3.5 h-3.5 text-zinc-400 group-hover/path:text-emerald-500 transition-colors shrink-0 path-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                            </svg>
                                            <span class="path-text text-[11px] font-mono truncate max-w-[280px] text-zinc-500 dark:text-zinc-400">
                                                {{ basename($activeProject->absolute_path) ?: 'Path' }}
                                            </span>
                                        </button>

                                        <!-- Hover Floating Tooltip untuk melihat path lengkap -->
                                        <div class="pointer-events-none opacity-0 group-hover/path:opacity-100 transition-opacity duration-150 absolute left-0 bottom-full mb-1.5 z-40 whitespace-nowrap bg-zinc-900 dark:bg-zinc-950 text-zinc-200 text-[11px] font-mono px-2.5 py-1 rounded-md shadow-xl border border-zinc-700/60 flex items-center gap-1.5">
                                            <svg class="w-3 h-3 text-zinc-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                                            </svg>
                                            <span>{{ $activeProject->absolute_path }}</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Smart Decision Engine Action Button (TASK-M2-04) -->
                    <div class="shrink-0 flex items-center self-start sm:self-center">
                        @if($nextAction['action_type'] === 'modal_scaffold')
                            <button type="button" onclick="openScaffoldModal('{{ $activeProject->id }}', '{{ addslashes($activeProject->project_name) }}', '{{ $activeFw }}')"
                                class="w-full sm:w-auto px-5 py-2.5 bg-[#3ECF8E] hover:bg-[#34b27b] text-zinc-950 font-bold rounded-xl text-xs transition-all shadow-md flex items-center justify-center gap-2 cursor-pointer group"
                                title="{{ $nextAction['tooltip'] }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                <span>{{ $nextAction['text'] }}</span>
                            </button>
                        @elseif($nextAction['action_type'] === 'open_editor')
                            <button type="button" onclick="openEditor('{{ $activeProject->id }}', '{{ $nextAction['editor'] ?? 'vscode' }}')"
                                class="w-full sm:w-auto px-5 py-2.5 bg-black text-white hover:bg-zinc-800 dark:bg-white dark:text-black dark:hover:bg-zinc-200 font-bold rounded-xl text-xs transition-colors shadow-xs flex items-center justify-center gap-2 cursor-pointer"
                                title="{{ $nextAction['tooltip'] }}">
                                <span>{{ $nextAction['text'] }}</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                            </button>
                        @else
                            <a href="{{ $nextAction['url'] }}"
                                class="w-full sm:w-auto px-5 py-2.5 {{ $nextAction['re_injection'] ? 'bg-amber-500 hover:bg-amber-600 text-zinc-950 font-bold animate-pulse' : 'bg-black text-white hover:bg-zinc-800 dark:bg-white dark:text-black dark:hover:bg-zinc-200 font-bold' }} rounded-xl text-xs transition-colors shadow-xs flex items-center justify-center gap-2 cursor-pointer"
                                title="{{ $nextAction['tooltip'] }}">
                                <span>{{ $nextAction['text'] }}</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <!-- 3. WORKSPACE SECTION: Project Saya -->
        <div class="space-y-4 pt-1">
            <!-- Section Header & Total Counter -->
            <div class="flex items-baseline justify-between">
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-zinc-900 dark:text-white tracking-tight">Project Saya</h2>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Daftar semua project yang terhubung dengan DEVArchitect.</p>
                </div>
                <span id="project-count-badge" class="text-xs font-semibold text-zinc-400 dark:text-zinc-500">{{ $projects->count() }} project</span>
            </div>

            <!-- Toolbar: Search, Status, Sort & View Switcher -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 pt-1">
                <!-- Sisi Kiri: Search Input + Status Filter + Sort Filter -->
                <div class="flex items-center gap-2.5 flex-wrap flex-1 min-w-0">
                    <!-- Search Input -->
                    <div class="relative flex-1 min-w-[200px] max-w-xs">
                        <svg class="w-3.5 h-3.5 text-zinc-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <input type="text" id="project-search-input" oninput="filterAndSortProjects()" placeholder="Cari project..."
                            class="w-full pl-8.5 pr-3 py-1.5 bg-white dark:bg-[#0c0c0e] border border-zinc-200 dark:border-zinc-800 rounded-lg text-xs text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:border-zinc-400 dark:focus:border-zinc-600 transition-colors shadow-2xs">
                    </div>

                    <!-- Status Filter -->
                    <div class="relative">
                        <select id="project-status-filter" onchange="filterAndSortProjects()"
                            class="appearance-none bg-white dark:bg-[#0c0c0e] border border-zinc-200 dark:border-zinc-800 rounded-lg text-xs font-medium text-zinc-700 dark:text-zinc-300 pl-3 pr-7 py-1.5 focus:outline-none focus:border-zinc-400 dark:focus:border-zinc-600 transition-colors cursor-pointer shadow-2xs">
                            <option value="all">Semua Status</option>
                            <option value="active">Sedang Dikerjakan</option>
                            <option value="standby">Standby</option>
                        </select>
                        <svg class="w-3 h-3 text-zinc-400 absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>

                    <!-- Sort Filter -->
                    <div class="relative">
                        <select id="project-sort-filter" onchange="filterAndSortProjects()"
                            class="appearance-none bg-white dark:bg-[#0c0c0e] border border-zinc-200 dark:border-zinc-800 rounded-lg text-xs font-medium text-zinc-700 dark:text-zinc-300 pl-7 pr-7 py-1.5 focus:outline-none focus:border-zinc-400 dark:focus:border-zinc-600 transition-colors cursor-pointer shadow-2xs">
                            <option value="name_asc">Urutkan: Nama (A-Z)</option>
                            <option value="name_desc">Urutkan: Nama (Z-A)</option>
                            <option value="updated_desc">Urutkan: Terakhir Diubah</option>
                        </select>
                        <svg class="w-3 h-3 text-zinc-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"></path>
                        </svg>
                        <svg class="w-3 h-3 text-zinc-400 absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                </div>

                <!-- Sisi Kanan: Toggle View Grid / List -->
                <div class="flex items-center gap-2 shrink-0 self-end sm:self-auto">
                    <div class="flex items-center bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg p-0.5 shadow-2xs">
                        <button type="button" id="btn-view-grid" onclick="switchProjectView('grid')"
                            class="p-1.5 rounded text-zinc-900 dark:text-white bg-white dark:bg-zinc-800 shadow-2xs transition-colors cursor-pointer"
                            title="Tampilan Grid">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M4 4h7v7H4V4zm9 0h7v7h-7V4zm-9 9h7v7H4v-7zm9 0h7v7h-7v-7z"/>
                            </svg>
                        </button>
                        <button type="button" id="btn-view-list" onclick="switchProjectView('list')"
                            class="p-1.5 rounded text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 transition-colors cursor-pointer"
                            title="Tampilan List">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            @if($projects->isEmpty())
                <!-- State Kosong (Empty State) -->
                <div class="bg-white dark:bg-[#0c0c0e] border border-zinc-200 dark:border-zinc-800 rounded-2xl p-12 text-center shadow-xs space-y-4 transition-colors">
                    <div class="w-12 h-12 rounded-xl bg-zinc-100 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center mx-auto text-zinc-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="max-w-sm mx-auto">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Belum Ada Project Terdaftar</h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Buka folder project yang ada di komputer Anda atau buat project baru dari nol.</p>
                    </div>
                    <button type="button" onclick="openAddModal()"
                        class="px-4 py-2 bg-[#3ECF8E] hover:bg-[#34b27b] text-zinc-950 font-semibold rounded-xl text-xs transition-colors shadow-xs inline-flex items-center gap-2 cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        <span>+ Tambah Project Baru</span>
                    </button>
                </div>
            @else
                <!-- A. GRID VIEW CONTAINER (Default) -->
                <div id="projects-grid-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($projects as $proj)
                        @php
                            $projFw = is_string($proj->framework_type) ? $proj->framework_type : ($proj->framework_type?->value ?? 'laravel');
                            $projLabel = match($projFw) {
                                'laravel' => 'Laravel',
                                'express_prisma' => 'Express + Prisma',
                                'express_drizzle' => 'Express + Drizzle',
                                'springboot_hibernate' => 'Spring Boot',
                                'raw_sql' => 'Raw SQL',
                                default => ucfirst((string)$projFw),
                            };
                            $dbDialect = $proj->database_dialect?->label()
                                ?? ($proj->latestGeneration?->database_dialect?->label()
                                    ?? match($projFw) {
                                        'laravel' => 'SQLite',
                                        'express_prisma' => 'PostgreSQL',
                                        'express_drizzle' => 'PostgreSQL',
                                        'springboot_hibernate' => 'PostgreSQL',
                                        default => 'SQL'
                                    });
                            $isActive = ($activeProject && $activeProject->id === $proj->id);
                            $isDraft = $proj->isDraft();
                            $needsReinjection = $proj->needsReinjection();
                        @endphp
                        <div class="project-item group relative bg-white dark:bg-[#0c0c0e] border {{ $isActive ? 'border-emerald-500/50 dark:border-emerald-500/40 ring-1 ring-emerald-500/20 shadow-xs' : 'border-zinc-200 dark:border-zinc-800/90 hover:border-zinc-300 dark:hover:border-zinc-700' }} rounded-xl p-4.5 transition-all flex flex-col justify-between min-h-[185px]"
                             data-name="{{ strtolower($proj->project_name) }}"
                             data-path="{{ strtolower($proj->absolute_path ?? '') }}"
                             data-status="{{ $isActive ? 'active' : 'standby' }}"
                             data-updated="{{ $proj->updated_at->timestamp }}">

                            <!-- Top: Icon, Title & 3-Dots Menu -->
                            <div>
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <div class="w-10 h-10 rounded-lg border border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-950 flex items-center justify-center p-2 shrink-0 shadow-2xs">
                                            <x-icon-framework :framework="$proj->framework_type" class="w-6 h-6 object-contain" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <h3 class="font-bold text-sm text-zinc-900 dark:text-white truncate group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors" title="{{ $proj->project_name }}">
                                                    {{ $proj->project_name }}
                                                </h3>
                                                @if($isDraft)
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">Draft</span>
                                                @endif
                                            </div>
                                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 flex items-center gap-1.5 truncate">
                                                <span>{{ $projLabel }}</span>
                                                <span class="text-zinc-300 dark:text-zinc-700">·</span>
                                                <span>{{ $dbDialect }}</span>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Popover Menu Titik 3 -->
                                    <div class="relative shrink-0 card-popover-wrapper">
                                        <button type="button" onclick="toggleCardMenu('grid-menu-{{ $proj->id }}', event)"
                                            class="p-1 text-zinc-400 hover:text-zinc-900 dark:hover:text-white rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors cursor-pointer"
                                            title="Opsi Project">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                                            </svg>
                                        </button>

                                        <!-- Popover Dropdown -->
                                        <div id="grid-menu-{{ $proj->id }}"
                                            class="hidden card-popover absolute right-0 top-full mt-1 w-48 rounded-xl bg-white dark:bg-[#121215] border border-zinc-200 dark:border-zinc-800 shadow-xl z-30 py-1 text-xs divide-y divide-zinc-100 dark:divide-zinc-800">
                                            <div class="py-1">
                                                @if(!$isActive)
                                                    <button type="button" onclick="setActiveProject('{{ $proj->id }}')"
                                                        class="w-full text-left px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-emerald-600 dark:text-emerald-400 font-medium flex items-center gap-2 transition-colors cursor-pointer">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                        <span>Jadikan Sedang Dikerjakan</span>
                                                    </button>
                                                @endif
                                                @if(!$isDraft)
                                                    <button type="button" onclick="openEditor('{{ $proj->id }}', 'vscode')"
                                                        class="w-full text-left px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300 flex items-center gap-2 transition-colors cursor-pointer">
                                                        <x-icon-editor editor="vscode" />
                                                        <span>Buka di VS Code</span>
                                                    </button>
                                                    <button type="button" onclick="openEditor('{{ $proj->id }}', 'terminal')"
                                                        class="w-full text-left px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300 flex items-center gap-2 transition-colors cursor-pointer">
                                                        <x-icon-editor editor="terminal" />
                                                        <span>Buka Terminal</span>
                                                    </button>
                                                    <button type="button" onclick="openEditor('{{ $proj->id }}', 'explorer')"
                                                        class="w-full text-left px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300 flex items-center gap-2 transition-colors cursor-pointer">
                                                        <x-icon-editor editor="explorer" />
                                                        <span>Buka di File Explorer</span>
                                                    </button>
                                                    <button type="button" onclick="copyToClipboard('{{ addslashes($proj->absolute_path ?? '') }}')"
                                                        class="w-full text-left px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300 flex items-center gap-2 transition-colors cursor-pointer">
                                                        <svg class="w-3.5 h-3.5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                                        <span>Salin Path Folder</span>
                                                    </button>
                                                @else
                                                    <button type="button" onclick="openScaffoldModal('{{ $proj->id }}', '{{ addslashes($proj->project_name) }}', '{{ $projFw }}')"
                                                        class="w-full text-left px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-[#3ECF8E] font-medium flex items-center gap-2 transition-colors cursor-pointer">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                                        <span>Instal ke Folder Lokal</span>
                                                    </button>
                                                @endif
                                            </div>
                                            <div class="py-1">
                                                <button type="button" onclick="confirmDeleteProject('{{ $proj->id }}', '{{ addslashes($proj->project_name) }}')"
                                                    class="w-full text-left px-3 py-1.5 hover:bg-red-500/10 text-red-600 dark:text-red-400 flex items-center gap-2 transition-colors cursor-pointer">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    <span>Hapus Project</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Stepper Progress (TASK-M2-03) -->
                                <div class="mt-2.5">
                                    <x-stepper-progress :progress="$proj->lifecycle_progress" :needs-reinjection="$needsReinjection" variant="compact" />
                                </div>

                                <!-- Middle: Path Bar Sederhana / Draft Notice -->
                                <div class="mt-2.5">
                                    @if($isDraft)
                                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] bg-amber-500/5 text-amber-600 dark:text-amber-400 border border-amber-500/20 w-full">
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            <span class="truncate">Belum diinstal ke folder lokal</span>
                                        </div>
                                    @else
                                        <div class="relative group/path inline-flex items-center w-full">
                                            <button type="button" 
                                                onclick="copyPathWithFeedback('{{ addslashes($proj->absolute_path) }}', this, event)"
                                                class="w-full inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono bg-zinc-50 hover:bg-zinc-100 dark:bg-zinc-900/60 dark:hover:bg-zinc-800 text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 border border-zinc-200/80 dark:border-zinc-800 transition-all cursor-pointer shadow-2xs"
                                                title="Klik untuk salin path folder">
                                                <svg class="w-3.5 h-3.5 text-zinc-400 group-hover/path:text-emerald-500 transition-colors shrink-0 path-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                                </svg>
                                                <span class="path-text text-[11px] font-mono truncate text-zinc-500 dark:text-zinc-400">
                                                    {{ basename($proj->absolute_path) ?: 'Path' }}
                                                </span>
                                            </button>

                                            <!-- Hover Floating Tooltip -->
                                            <div class="pointer-events-none opacity-0 group-hover/path:opacity-100 transition-opacity duration-150 absolute left-0 bottom-full mb-1.5 z-40 whitespace-nowrap bg-zinc-900 dark:bg-zinc-950 text-zinc-200 text-[11px] font-mono px-2.5 py-1 rounded-md shadow-xl border border-zinc-700/60 flex items-center gap-1.5">
                                                <svg class="w-3 h-3 text-zinc-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                                                </svg>
                                                <span>{{ $proj->absolute_path }}</span>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Bottom Bar: Status Indikator & Tombol Buka Project -->
                            <div class="flex items-center justify-between pt-3 mt-3 border-t border-zinc-100 dark:border-zinc-800/80 text-xs">
                                <div>
                                    @if($isActive)
                                        <div class="flex items-center gap-1.5 text-xs font-semibold text-zinc-900 dark:text-white whitespace-nowrap">
                                            <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                                            <span>Sedang Dikerjakan</span>
                                        </div>
                                    @else
                                        <span class="text-[11px] text-zinc-400 dark:text-zinc-500">Standby</span>
                                    @endif
                                </div>

                                <div>
                                    @if($isDraft)
                                        <button type="button" onclick="openScaffoldModal('{{ $proj->id }}', '{{ addslashes($proj->project_name) }}', '{{ $projFw }}')"
                                            class="px-3.5 py-1.5 bg-[#3ECF8E] hover:bg-[#34b27b] text-zinc-950 font-bold rounded-lg text-xs transition-colors shadow-2xs flex items-center gap-1.5 cursor-pointer"
                                            title="Instal project ke folder komputer">
                                            <span>Instal</span>
                                            <span class="text-[10px]">⚡</span>
                                        </button>
                                    @elseif($isActive)
                                        <a href="{{ url('/generator') }}"
                                            class="px-3.5 py-1.5 bg-black text-white hover:bg-zinc-800 dark:bg-white dark:text-black dark:hover:bg-zinc-200 font-semibold rounded-lg text-xs shadow-2xs transition-colors flex items-center gap-1 cursor-pointer">
                                            <span>Buka Project</span>
                                            <span class="text-[10px]">→</span>
                                        </a>
                                    @else
                                        <button type="button" onclick="openProjectAndGo('{{ $proj->id }}')"
                                            class="px-3.5 py-1.5 bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-900 dark:text-white font-semibold rounded-lg text-xs transition-colors shadow-2xs flex items-center gap-1 cursor-pointer"
                                            title="Jadikan project aktif dan buka">
                                            <span>Buka Project</span>
                                            <span class="text-[10px]">→</span>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- B. LIST VIEW CONTAINER (Preserved) -->
                <div id="projects-list-container" class="hidden grid grid-cols-1 gap-3">
                    @foreach($projects as $proj)
                        @php
                            $projFw = is_string($proj->framework_type) ? $proj->framework_type : ($proj->framework_type?->value ?? 'laravel');
                            $projLabel = match($projFw) {
                                'laravel' => 'Laravel',
                                'express_prisma' => 'Express + Prisma',
                                'express_drizzle' => 'Express + Drizzle',
                                'springboot_hibernate' => 'Spring Boot',
                                'raw_sql' => 'Raw SQL',
                                default => ucfirst((string)$projFw),
                            };
                            $dbDialect = $proj->database_dialect?->label()
                                ?? ($proj->latestGeneration?->database_dialect?->label()
                                    ?? match($projFw) {
                                        'laravel' => 'SQLite',
                                        'express_prisma' => 'PostgreSQL',
                                        'express_drizzle' => 'PostgreSQL',
                                        'springboot_hibernate' => 'PostgreSQL',
                                        default => 'SQL'
                                    });
                            $isActive = ($activeProject && $activeProject->id === $proj->id);
                            $isDraft = $proj->isDraft();
                            $needsReinjection = $proj->needsReinjection();
                        @endphp
                        <div class="project-item bg-white dark:bg-[#0c0c0e] border {{ $isActive ? 'border-zinc-200 dark:border-zinc-800 border-l-4 border-l-emerald-500 dark:border-l-emerald-500' : 'border-zinc-200 dark:border-zinc-800' }} rounded-xl p-4 shadow-xs transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-4"
                             data-name="{{ strtolower($proj->project_name) }}"
                             data-path="{{ strtolower($proj->absolute_path ?? '') }}"
                             data-status="{{ $isActive ? 'active' : 'standby' }}"
                             data-updated="{{ $proj->updated_at->timestamp }}">

                            <!-- Left: Framework Icon & Info -->
                            <div class="flex items-center gap-3.5 min-w-0 flex-1">
                                <div class="w-10 h-10 rounded-lg border border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-950 flex items-center justify-center p-2 shrink-0 shadow-2xs">
                                    <x-icon-framework :framework="$proj->framework_type" class="w-6 h-6 object-contain" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h3 class="font-bold text-sm text-zinc-900 dark:text-white truncate" title="{{ $proj->project_name }}">
                                            {{ $proj->project_name }}
                                        </h3>
                                        <span class="px-2 py-0.5 rounded text-[11px] font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300">
                                            {{ $projLabel }} · {{ $dbDialect }}
                                        </span>
                                        @if($isDraft)
                                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">Draft</span>
                                        @endif
                                        @if($isActive)
                                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-zinc-900 dark:text-white">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                                <span>Sedang Dikerjakan</span>
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3 mt-1.5">
                                        <x-stepper-progress :progress="$proj->lifecycle_progress" :needs-reinjection="$needsReinjection" variant="compact" />
                                        @if(!$isDraft)
                                            <span class="text-[11px] font-mono text-zinc-400 dark:text-zinc-500 truncate" title="{{ $proj->absolute_path }}">
                                                {{ $proj->absolute_path }}
                                            </span>
                                        @else
                                            <span class="text-[11px] text-amber-600 dark:text-amber-400">
                                                (Belum diinstal ke folder komputer)
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Action Buttons -->
                            <div class="flex items-center gap-2 shrink-0 self-end sm:self-auto">
                                @if($isDraft)
                                    <button type="button" onclick="openScaffoldModal('{{ $proj->id }}', '{{ addslashes($proj->project_name) }}', '{{ $projFw }}')"
                                        class="px-3.5 py-1.5 bg-[#3ECF8E] hover:bg-[#34b27b] text-zinc-950 font-bold rounded-lg text-xs shadow-2xs transition-colors flex items-center gap-1 cursor-pointer"
                                        title="Instal project ke folder komputer">
                                        <span>Instal</span>
                                        <span class="text-[10px]">⚡</span>
                                    </button>
                                @elseif($isActive)
                                    <a href="{{ url('/generator') }}"
                                        class="px-3.5 py-1.5 bg-black text-white hover:bg-zinc-800 dark:bg-white dark:text-black dark:hover:bg-zinc-200 font-semibold rounded-lg text-xs shadow-2xs transition-colors flex items-center gap-1 cursor-pointer">
                                        <span>Buka Project</span>
                                        <span class="text-[10px]">→</span>
                                    </a>
                                @else
                                    <button type="button" onclick="openProjectAndGo('{{ $proj->id }}')"
                                        class="px-3.5 py-1.5 bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-900 dark:text-white font-semibold rounded-lg text-xs transition-colors shadow-2xs flex items-center gap-1 cursor-pointer">
                                        <span>Buka Project</span>
                                        <span class="text-[10px]">→</span>
                                    </button>
                                @endif

                                <!-- More Dropdown -->
                                <div class="relative card-popover-wrapper">
                                    <button type="button" onclick="toggleCardMenu('list-menu-{{ $proj->id }}', event)"
                                        class="p-1.5 text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors cursor-pointer"
                                        title="Opsi Project">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                                        </svg>
                                    </button>

                                    <div id="list-menu-{{ $proj->id }}"
                                        class="hidden card-popover absolute right-0 top-full mt-1 w-48 rounded-xl bg-white dark:bg-[#121215] border border-zinc-200 dark:border-zinc-800 shadow-xl z-30 py-1 text-xs divide-y divide-zinc-100 dark:divide-zinc-800">
                                        <div class="py-1">
                                            @if(!$isDraft)
                                                <button type="button" onclick="openEditor('{{ $proj->id }}', 'vscode')"
                                                    class="w-full text-left px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300 flex items-center gap-2 transition-colors cursor-pointer">
                                                    <x-icon-editor editor="vscode" />
                                                    <span>Buka di VS Code</span>
                                                </button>
                                                <button type="button" onclick="openEditor('{{ $proj->id }}', 'terminal')"
                                                    class="w-full text-left px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300 flex items-center gap-2 transition-colors cursor-pointer">
                                                    <x-icon-editor editor="terminal" />
                                                    <span>Buka Terminal</span>
                                                </button>
                                                <button type="button" onclick="openEditor('{{ $proj->id }}', 'explorer')"
                                                    class="w-full text-left px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300 flex items-center gap-2 transition-colors cursor-pointer">
                                                    <x-icon-editor editor="explorer" />
                                                    <span>Buka di File Explorer</span>
                                                </button>
                                                <button type="button" onclick="copyToClipboard('{{ addslashes($proj->absolute_path ?? '') }}')"
                                                    class="w-full text-left px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300 flex items-center gap-2 transition-colors cursor-pointer">
                                                    <svg class="w-3.5 h-3.5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                                    <span>Salin Path Folder</span>
                                                </button>
                                            @else
                                                <button type="button" onclick="openScaffoldModal('{{ $proj->id }}', '{{ addslashes($proj->project_name) }}', '{{ $projFw }}')"
                                                    class="w-full text-left px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-[#3ECF8E] font-medium flex items-center gap-2 transition-colors cursor-pointer">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                                    <span>Instal ke Folder Lokal</span>
                                                </button>
                                            @endif
                                        </div>
                                        <div class="py-1">
                                            <button type="button" onclick="confirmDeleteProject('{{ $proj->id }}', '{{ addslashes($proj->project_name) }}')"
                                                class="w-full text-left px-3 py-1.5 hover:bg-red-500/10 text-red-600 dark:text-red-400 flex items-center gap-2 transition-colors cursor-pointer">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                <span>Hapus Project</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- C. Empty Search Filter State -->
                <div id="projects-no-results" class="hidden bg-white dark:bg-[#0c0c0e] border border-zinc-200 dark:border-zinc-800 rounded-xl p-10 text-center space-y-2">
                    <svg class="w-8 h-8 text-zinc-400 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <p class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Tidak ada project yang sesuai dengan pencarian atau filter</p>
                </div>
            @endif
        </div>

        <!-- 4. ASISTEN DOKUMEN AI SECTION: Di Bawah Daftar Project -->
        <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-[#0c0c0e] p-5 sm:p-6 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-5 transition-all">
            <div class="flex items-start gap-4">
                <div class="w-11 h-11 rounded-xl bg-black text-white dark:bg-white dark:text-black flex items-center justify-center shrink-0 shadow-2xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm sm:text-base font-bold text-zinc-900 dark:text-white">Asisten Dokumen AI</h3>
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-medium bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700/80 text-zinc-600 dark:text-zinc-300">
                            AI Assistant
                        </span>
                    </div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 max-w-xl">
                        Bantu merancang project, alur fitur, dan dokumentasi arsitektur aplikasi Anda dengan bantuan AI.
                    </p>
                    <div class="text-[11px] text-zinc-400 mt-2 flex items-center gap-2">
                        <span>{{ $docTotal }} dokumen tersimpan</span>
                        @if($docActive > 0)
                            <span class="text-zinc-300 dark:text-zinc-700">•</span>
                            <span class="text-emerald-500 font-medium">{{ $docActive }} aktif</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="shrink-0">
                <a href="{{ url('/assistant') }}"
                    class="w-full sm:w-auto px-4 py-2 bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-900 dark:hover:bg-zinc-800 text-zinc-900 dark:text-white font-semibold rounded-xl text-xs border border-zinc-200 dark:border-zinc-800 transition-colors flex items-center justify-center gap-1.5 shadow-2xs cursor-pointer">
                    <span>Buka Asisten</span>
                    <span class="text-zinc-400">→</span>
                </a>
            </div>
        </div>

    </div>

    <!-- ================= PARTIAL MODALS ================= -->
    @include('dashboard.partials.add-project-modal')
    @include('dashboard.partials.delete-project-modal')
@endsection

@push('scripts')
    @vite(['resources/js/dashboard.js'])
@endpush