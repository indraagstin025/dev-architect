<!DOCTYPE html>
<html lang="id" class="dark h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="desktop-bridge-token" content="{{ \App\Models\AppSetting::getOrCreateDesktopBridgeKey() }}">
    <title>{{ $title ?? 'Dashboard' }} — DEVArchitect</title>
    
    <!-- Modern Sans & Mono Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Anti-FOUC Theme, Window State & Zoom Initializer -->
    <script>
        (function() {
            try {
                const theme = localStorage.getItem('devarchitect_theme') || 'dark';
                if (theme === 'dark') {
                    document.documentElement.classList.add('dark');
                    document.documentElement.classList.remove('light');
                } else {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.classList.add('light');
                }

                if (localStorage.getItem('devarchitect_is_maximized') === 'true') {
                    document.documentElement.classList.add('is-window-maximized');
                }

                const savedZoom = localStorage.getItem('devarchitect_app_zoom');
                if (savedZoom) {
                    document.documentElement.style.zoom = savedZoom;
                }
            } catch (e) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-zinc-50 dark:bg-black text-zinc-900 dark:text-zinc-100 font-sans antialiased overflow-hidden flex flex-col select-none transition-colors duration-200">

    @php
        $hasApiKey = !empty(\App\Models\AppSetting::get('openrouter_api_key')) || !empty(config('services.openrouter.key'));
        $activeProjectId = \App\Models\AppSetting::get('active_project_id');
        $activeProject = $activeProjectId ? \App\Models\Project::find($activeProjectId) : \App\Models\Project::orderBy('updated_at', 'desc')->first();
    @endphp

    <!-- 0. Frameless Modern Desktop Titlebar (VS Code / Discord Style) -->
    <header id="desktop-titlebar" class="bg-white dark:bg-[#0c0c0e] border-b border-zinc-200 dark:border-zinc-800/90 flex items-center justify-between text-xs select-none transition-colors duration-200 shrink-0 relative z-30">
        
        <!-- Sisi Kiri: Brand + Menu Bar (File, Edit, View, Window, Help) -->
        <div class="app-no-drag flex items-center h-full pl-2.5 gap-1 relative z-20 pointer-events-auto">
            <!-- App Small Icon -->
            <div class="flex items-center gap-1.5 pr-1.5">
                <div class="w-4.5 h-4.5 rounded bg-black text-white dark:bg-white dark:text-black flex items-center justify-center font-bold text-[10px] shadow-2xs shrink-0">
                    <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                    </svg>
                </div>
                <span class="font-bold text-[11px] tracking-tight text-zinc-900 dark:text-white">DEVArchitect</span>
            </div>

            <div class="w-px h-3 bg-zinc-200 dark:bg-zinc-800 mx-1"></div>

            <!-- Titlebar Menu Item: File -->
            <div class="relative titlebar-menu-item">
                <button type="button" class="titlebar-menu-trigger px-2 py-1 rounded text-[11px] text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:text-zinc-100 dark:hover:bg-zinc-800/70 transition-colors">
                    File
                </button>
                <div class="titlebar-menu-dropdown hidden absolute left-0 top-full mt-1 w-64 bg-white/95 dark:bg-[#121215]/95 border border-zinc-200 dark:border-zinc-800 rounded-lg shadow-2xl backdrop-blur-md py-1.5 z-50 text-[11px]">
                    <a href="{{ url('/assistant') }}" class="flex items-center justify-between px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 text-zinc-700 dark:text-zinc-200 hover:text-zinc-900 dark:hover:text-white transition-colors">
                        <span class="flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            <span>Asisten Dokumen AI</span>
                        </span>
                        <kbd class="text-[10px] text-zinc-400 font-mono">Ctrl+1</kbd>
                    </a>
                    <a href="{{ url('/assistant') }}?new=1" class="flex items-center justify-between px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 text-zinc-700 dark:text-zinc-200 hover:text-zinc-900 dark:hover:text-white transition-colors">
                        <span>Buka Dokumen Baru</span>
                        <kbd class="text-[10px] text-zinc-400 font-mono">Ctrl+N</kbd>
                    </a>
                    <div class="my-1 border-t border-zinc-100 dark:border-zinc-800/80"></div>
                    <a href="{{ url('/dashboard') }}" class="flex items-center justify-between px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 text-zinc-700 dark:text-zinc-200 hover:text-zinc-900 dark:hover:text-white transition-colors">
                        <span>Daftar Proyek</span>
                        <kbd class="text-[10px] text-zinc-400 font-mono">Ctrl+2</kbd>
                    </a>
                    <a href="{{ url('/generator') }}" class="flex items-center justify-between px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 text-zinc-700 dark:text-zinc-200 hover:text-zinc-900 dark:hover:text-white transition-colors">
                        <span>Pembuat Database (Generator)</span>
                        <kbd class="text-[10px] text-zinc-400 font-mono">Ctrl+3</kbd>
                    </a>
                    <a href="{{ url('/history') }}" class="flex items-center justify-between px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 text-zinc-700 dark:text-zinc-200 hover:text-zinc-900 dark:hover:text-white transition-colors">
                        <span>Riwayat Pembuatan</span>
                        <kbd class="text-[10px] text-zinc-400 font-mono">Ctrl+4</kbd>
                    </a>
                    <div class="my-1 border-t border-zinc-100 dark:border-zinc-800/80"></div>
                    <a href="{{ url('/settings') }}" class="flex items-center justify-between px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 text-zinc-700 dark:text-zinc-200 hover:text-zinc-900 dark:hover:text-white transition-colors">
                        <span>Pengaturan & Kunci AI</span>
                        <kbd class="text-[10px] text-zinc-400 font-mono">Ctrl+,</kbd>
                    </a>
                    <div class="my-1 border-t border-zinc-100 dark:border-zinc-800/80"></div>
                    <button type="button" onclick="window.desktopCloseWindow()" class="w-full flex items-center justify-between px-3 py-1.5 hover:bg-red-500/10 text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 transition-colors text-left">
                        <span>Keluar dari DEVArchitect</span>
                        <kbd class="text-[10px] text-zinc-400 font-mono">Alt+F4</kbd>
                    </button>
                </div>
            </div>

            <!-- Titlebar Menu Item: Edit -->
            <div class="relative titlebar-menu-item">
                <button type="button" class="titlebar-menu-trigger px-2 py-1 rounded text-[11px] text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:text-zinc-100 dark:hover:bg-zinc-800/70 transition-colors">
                    Edit
                </button>
                <div class="titlebar-menu-dropdown hidden absolute left-0 top-full mt-1 w-52 bg-white/95 dark:bg-[#121215]/95 border border-zinc-200 dark:border-zinc-800 rounded-lg shadow-2xl backdrop-blur-md py-1.5 z-50 text-[11px]">
                    <button type="button" onclick="document.execCommand('undo')" class="w-full flex items-center justify-between px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 text-zinc-700 dark:text-zinc-200 hover:text-zinc-900 dark:hover:text-white transition-colors text-left">
                        <span>Undo (Batal)</span>
                        <kbd class="text-[10px] text-zinc-400 font-mono">Ctrl+Z</kbd>
                    </button>
                    <button type="button" onclick="document.execCommand('redo')" class="w-full flex items-center justify-between px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 text-zinc-700 dark:text-zinc-200 hover:text-zinc-900 dark:hover:text-white transition-colors text-left">
                        <span>Redo (Ulangi)</span>
                        <kbd class="text-[10px] text-zinc-400 font-mono">Ctrl+Y</kbd>
                    </button>
                    <div class="my-1 border-t border-zinc-100 dark:border-zinc-800/80"></div>
                    <button type="button" onclick="document.execCommand('cut')" class="w-full flex items-center justify-between px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 text-zinc-700 dark:text-zinc-200 hover:text-zinc-900 dark:hover:text-white transition-colors text-left">
                        <span>Cut (Potong)</span>
                        <kbd class="text-[10px] text-zinc-400 font-mono">Ctrl+X</kbd>
                    </button>
                    <button type="button" onclick="document.execCommand('copy')" class="w-full flex items-center justify-between px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 text-zinc-700 dark:text-zinc-200 hover:text-zinc-900 dark:hover:text-white transition-colors text-left">
                        <span>Copy (Salin)</span>
                        <kbd class="text-[10px] text-zinc-400 font-mono">Ctrl+C</kbd>
                    </button>
                    <button type="button" onclick="window.desktopPaste()" class="w-full flex items-center justify-between px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 text-zinc-700 dark:text-zinc-200 hover:text-zinc-900 dark:hover:text-white transition-colors text-left">
                        <span>Paste (Tempel)</span>
                        <kbd class="text-[10px] text-zinc-400 font-mono">Ctrl+V</kbd>
                    </button>
                    <div class="my-1 border-t border-zinc-100 dark:border-zinc-800/80"></div>
                    <button type="button" onclick="document.execCommand('selectAll')" class="w-full flex items-center justify-between px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 text-zinc-700 dark:text-zinc-200 hover:text-zinc-900 dark:hover:text-white transition-colors text-left">
                        <span>Pilih Semua</span>
                        <kbd class="text-[10px] text-zinc-400 font-mono">Ctrl+A</kbd>
                    </button>
                </div>
            </div>

            <!-- Titlebar Menu Item: View -->
            <div class="relative titlebar-menu-item">
                <button type="button" class="titlebar-menu-trigger px-2 py-1 rounded text-[11px] text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:text-zinc-100 dark:hover:bg-zinc-800/70 transition-colors">
                    View
                </button>
                <div class="titlebar-menu-dropdown hidden absolute left-0 top-full mt-1 w-56 bg-white/95 dark:bg-[#121215]/95 border border-zinc-200 dark:border-zinc-800 rounded-lg shadow-2xl backdrop-blur-md py-1.5 z-50 text-[11px]">
                    <button type="button" onclick="window.location.reload()" class="w-full flex items-center justify-between px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 text-zinc-700 dark:text-zinc-200 hover:text-zinc-900 dark:hover:text-white transition-colors text-left">
                        <span>Muat Ulang (Reload)</span>
                        <kbd class="text-[10px] text-zinc-400 font-mono">Ctrl+R</kbd>
                    </button>
                    <button type="button" onclick="window.desktopToggleFullscreen()" class="w-full flex items-center justify-between px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 text-zinc-700 dark:text-zinc-200 hover:text-zinc-900 dark:hover:text-white transition-colors text-left">
                        <span>Layar Penuh (Fullscreen)</span>
                        <kbd class="text-[10px] text-zinc-400 font-mono">F11</kbd>
                    </button>
                    <div class="my-1 border-t border-zinc-100 dark:border-zinc-800/80"></div>
                    <button type="button" onclick="window.zoomApp(0.05)" class="w-full flex items-center justify-between px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 text-zinc-700 dark:text-zinc-200 hover:text-zinc-900 dark:hover:text-white transition-colors text-left">
                        <span>Perbesar Tampilan (Zoom In)</span>
                        <kbd class="text-[10px] text-zinc-400 font-mono">Ctrl++</kbd>
                    </button>
                    <button type="button" onclick="window.zoomApp(-0.05)" class="w-full flex items-center justify-between px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 text-zinc-700 dark:text-zinc-200 hover:text-zinc-900 dark:hover:text-white transition-colors text-left">
                        <span>Perkecil Tampilan (Zoom Out)</span>
                        <kbd class="text-[10px] text-zinc-400 font-mono">Ctrl+-</kbd>
                    </button>
                    <button type="button" onclick="window.zoomApp(0)" class="w-full flex items-center justify-between px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 text-zinc-700 dark:text-zinc-200 hover:text-zinc-900 dark:hover:text-white transition-colors text-left">
                        <span>Reset Skala (Default 90%)</span>
                        <kbd class="text-[10px] text-zinc-400 font-mono">Ctrl+0</kbd>
                    </button>
                    <div class="my-1 border-t border-zinc-100 dark:border-zinc-800/80"></div>
                    <button type="button" onclick="window.toggleTheme()" class="w-full flex items-center justify-between px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 text-zinc-700 dark:text-zinc-200 hover:text-zinc-900 dark:hover:text-white transition-colors text-left">
                        <span>Ganti Tema (Dark / Light)</span>
                        <span class="text-[10px] text-zinc-400">🌓</span>
                    </button>
                </div>
            </div>

            <!-- Titlebar Menu Item: Window -->
            <div class="relative titlebar-menu-item">
                <button type="button" class="titlebar-menu-trigger px-2 py-1 rounded text-[11px] text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:text-zinc-100 dark:hover:bg-zinc-800/70 transition-colors">
                    Window
                </button>
                <div class="titlebar-menu-dropdown hidden absolute left-0 top-full mt-1 w-48 bg-white/95 dark:bg-[#121215]/95 border border-zinc-200 dark:border-zinc-800 rounded-lg shadow-2xl backdrop-blur-md py-1.5 z-50 text-[11px]">
                    <button type="button" onclick="window.desktopMinimizeWindow()" class="w-full flex items-center justify-between px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 text-zinc-700 dark:text-zinc-200 hover:text-zinc-900 dark:hover:text-white transition-colors text-left">
                        <span>Minimalkan</span>
                        <span class="text-[10px] text-zinc-400">_</span>
                    </button>
                    <button type="button" onclick="window.desktopMaximizeWindow()" class="w-full flex items-center justify-between px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 text-zinc-700 dark:text-zinc-200 hover:text-zinc-900 dark:hover:text-white transition-colors text-left">
                        <span>Maksimalkan</span>
                        <span class="text-[10px] text-zinc-400">□</span>
                    </button>
                    <div class="my-1 border-t border-zinc-100 dark:border-zinc-800/80"></div>
                    <button type="button" onclick="window.desktopCloseWindow()" class="w-full flex items-center justify-between px-3 py-1.5 hover:bg-red-500/10 text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 transition-colors text-left">
                        <span>Tutup Jendela</span>
                        <kbd class="text-[10px] text-zinc-400 font-mono">Alt+F4</kbd>
                    </button>
                </div>
            </div>

            <!-- Titlebar Menu Item: Help -->
            <div class="relative titlebar-menu-item">
                <button type="button" class="titlebar-menu-trigger px-2 py-1 rounded text-[11px] text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:text-zinc-100 dark:hover:bg-zinc-800/70 transition-colors">
                    Help
                </button>
                <div class="titlebar-menu-dropdown hidden absolute left-0 top-full mt-1 w-60 bg-white/95 dark:bg-[#121215]/95 border border-zinc-200 dark:border-zinc-800 rounded-lg shadow-2xl backdrop-blur-md py-1.5 z-50 text-[11px]">
                    <a href="{{ url('/settings') }}" class="flex items-center justify-between px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 text-zinc-700 dark:text-zinc-200 hover:text-zinc-900 dark:hover:text-white transition-colors">
                        <span>Status Kunci API AI</span>
                        <span class="w-1.5 h-1.5 rounded-full {{ $hasApiKey ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                    </a>
                    <button type="button" onclick="window.toast('Pintasan Global: Tekan Ctrl+Alt+A untuk menyembunyikan atau menampilkan DEVArchitect kapan saja.', 'info')" class="w-full flex items-center justify-between px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 text-zinc-700 dark:text-zinc-200 hover:text-zinc-900 dark:hover:text-white transition-colors text-left">
                        <span>Pintasan Global OS</span>
                        <kbd class="text-[10px] text-zinc-400 font-mono">Ctrl+Alt+A</kbd>
                    </button>
                    <div class="my-1 border-t border-zinc-100 dark:border-zinc-800/80"></div>
                    <button type="button" onclick="window.toast('DEVArchitect v2.0 - Universal AI Database Architect & Document Assistant', 'success')" class="w-full flex items-center justify-between px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 text-zinc-700 dark:text-zinc-200 hover:text-zinc-900 dark:hover:text-white transition-colors text-left">
                        <span>Tentang DEVArchitect</span>
                        <span class="text-[10px] text-emerald-500 font-mono">v2.0</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Sisi Tengah: Drag Region (kosong, bisa di-drag) -->
        <div class="flex-1 h-full app-drag-region cursor-default"></div>

        <!-- Sisi Kanan: Theme Toggle & Window Controls Modern -->
        <div class="app-no-drag flex items-center h-full relative z-20 pointer-events-auto">
            <!-- Quick Theme Switcher Button -->
            <button type="button" 
                    onclick="window.toggleTheme()" 
                    class="w-7 h-7 mr-1.5 rounded-md flex items-center justify-center text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:text-white dark:hover:bg-zinc-800 transition-colors"
                    title="Ganti Tema (Dark / Light)">
                <svg class="w-3.5 h-3.5 dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                <svg class="w-3.5 h-3.5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
            </button>

            <!-- Minimize Button -->
            <button type="button" 
                    id="win-min-btn"
                    onclick="window.desktopMinimizeWindow()" 
                    class="win-ctrl-btn text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white" 
                    title="Minimalkan">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M5 12h14v1.5H5z"/>
                </svg>
            </button>

            <!-- Maximize / Restore / Snap Multitasking Container -->
            <div class="relative" id="win-snap-container">
                <button type="button" 
                        id="win-max-btn"
                        onclick="window.desktopMaximizeWindow()" 
                        class="win-ctrl-btn text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white" 
                        title="Maksimalkan / Kembalikan Ukuran (Arahkan mouse untuk Menu Multitasking)">
                    <!-- Icon: Normal / Maximize (Default: Single Square) -->
                    <svg id="win-max-icon" class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <rect x="4" y="4" width="16" height="16" rx="1.5"/>
                    </svg>
                    <!-- Icon: Maximized / Restore (Double Overlapping Square) -->
                    <svg id="win-restore-icon" class="w-3 h-3 hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <rect x="7" y="7" width="13" height="13" rx="1.5"/>
                        <path d="M4 17V5a1 1 0 0 1 1-1h12"/>
                    </svg>
                </button>

                <!-- Modern Windows 11-Style Snap & Multitasking Layout Popup -->
                <div id="win-snap-menu" class="hidden absolute right-0 top-full pt-1.5 z-50 w-80 app-no-drag">
                    <div class="p-3.5 bg-white/98 dark:bg-[#121215]/98 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-2xl backdrop-blur-xl text-zinc-800 dark:text-zinc-200 text-xs">
                        <div class="flex items-center justify-between mb-2.5 pb-2 border-b border-zinc-100 dark:border-zinc-800/80">
                            <span class="text-[11px] font-semibold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-[#3ECF8E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M12 3v18"/></svg>
                                Mode Multitasking Layar
                            </span>
                            <span class="text-[10px] text-zinc-400 font-medium">Snap Assist</span>
                        </div>

                        <div class="grid grid-cols-2 gap-2.5">
                            <!-- 1. Belah Kiri 50% -->
                            <button type="button" onclick="event.stopPropagation(); window.desktopSnapWindow('left')" class="app-no-drag cursor-pointer group/tile flex flex-col items-center gap-1.5 p-2 rounded-lg bg-zinc-50/70 dark:bg-zinc-900/50 hover:bg-emerald-500/10 dark:hover:bg-[#3ECF8E]/15 border border-zinc-200/80 dark:border-zinc-800 hover:border-[#3ECF8E]/60 transition-all text-left">
                                <div class="w-full h-12 rounded border border-zinc-300 dark:border-zinc-700 bg-zinc-100/90 dark:bg-zinc-950 p-1 flex gap-1 pointer-events-none">
                                    <div class="w-1/2 h-full bg-[#3ECF8E]/40 group-hover/tile:bg-[#3ECF8E] rounded-xs border border-[#3ECF8E] transition-colors"></div>
                                    <div class="w-1/2 h-full bg-zinc-200/70 dark:bg-zinc-800/70 rounded-xs"></div>
                                </div>
                                <span class="text-[10px] font-medium text-zinc-700 dark:text-zinc-300 group-hover/tile:text-emerald-600 dark:group-hover/tile:text-[#3ECF8E] pointer-events-none">Bagi Kiri 50%</span>
                            </button>

                            <!-- 2. Belah Kanan 50% -->
                            <button type="button" onclick="event.stopPropagation(); window.desktopSnapWindow('right')" class="app-no-drag cursor-pointer group/tile flex flex-col items-center gap-1.5 p-2 rounded-lg bg-zinc-50/70 dark:bg-zinc-900/50 hover:bg-emerald-500/10 dark:hover:bg-[#3ECF8E]/15 border border-zinc-200/80 dark:border-zinc-800 hover:border-[#3ECF8E]/60 transition-all text-left">
                                <div class="w-full h-12 rounded border border-zinc-300 dark:border-zinc-700 bg-zinc-100/90 dark:bg-zinc-950 p-1 flex gap-1 pointer-events-none">
                                    <div class="w-1/2 h-full bg-zinc-200/70 dark:bg-zinc-800/70 rounded-xs"></div>
                                    <div class="w-1/2 h-full bg-[#3ECF8E]/40 group-hover/tile:bg-[#3ECF8E] rounded-xs border border-[#3ECF8E] transition-colors"></div>
                                </div>
                                <span class="text-[10px] font-medium text-zinc-700 dark:text-zinc-300 group-hover/tile:text-emerald-600 dark:group-hover/tile:text-[#3ECF8E] pointer-events-none">Bagi Kanan 50%</span>
                            </button>

                            <!-- 3. Skala Kompak / Floating Tengah (Scale Down) -->
                            <button type="button" onclick="event.stopPropagation(); window.desktopSnapWindow('compact')" class="app-no-drag cursor-pointer group/tile flex flex-col items-center gap-1.5 p-2 rounded-lg bg-zinc-50/70 dark:bg-zinc-900/50 hover:bg-blue-500/10 dark:hover:bg-blue-500/15 border border-zinc-200/80 dark:border-zinc-800 hover:border-blue-500/60 transition-all text-left">
                                <div class="w-full h-12 rounded border border-zinc-300 dark:border-zinc-700 bg-zinc-100/90 dark:bg-zinc-950 p-1 flex items-center justify-center pointer-events-none">
                                    <div class="w-3/4 h-3/4 bg-blue-500/40 group-hover/tile:bg-blue-500 rounded-xs border border-blue-500 transition-colors"></div>
                                </div>
                                <span class="text-[10px] font-medium text-zinc-700 dark:text-zinc-300 group-hover/tile:text-blue-600 dark:group-hover/tile:text-blue-400 pointer-events-none">Skala Kecil (Kompak)</span>
                            </button>

                            <!-- 4. Layar Penuh (Maksimal) -->
                            <button type="button" onclick="event.stopPropagation(); window.desktopSnapWindow('fullscreen')" class="app-no-drag cursor-pointer group/tile flex flex-col items-center gap-1.5 p-2 rounded-lg bg-zinc-50/70 dark:bg-zinc-900/50 hover:bg-purple-500/10 dark:hover:bg-purple-500/15 border border-zinc-200/80 dark:border-zinc-800 hover:border-purple-500/60 transition-all text-left">
                                <div class="w-full h-12 rounded border border-zinc-300 dark:border-zinc-700 bg-zinc-100/90 dark:bg-zinc-950 p-1 pointer-events-none">
                                    <div class="w-full h-full bg-purple-500/40 group-hover/tile:bg-purple-500 rounded-xs border border-purple-500 transition-colors"></div>
                                </div>
                                <span class="text-[10px] font-medium text-zinc-700 dark:text-zinc-300 group-hover/tile:text-purple-600 dark:group-hover/tile:text-purple-400 pointer-events-none">Layar Penuh</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Close Button -->
            <button type="button" 
                    id="win-close-btn"
                    onclick="window.desktopCloseWindow()" 
                    class="win-ctrl-btn win-close-btn text-zinc-600 hover:text-white dark:text-zinc-400 dark:hover:text-white" 
                    title="Tutup Jendela">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </header>

    <!-- 1. Radix-Style Global API Key Warning Banner -->
    <div id="global-api-warning" class="{{ $hasApiKey ? 'hidden' : '' }} bg-zinc-100 dark:bg-zinc-900/90 border-b border-zinc-200 dark:border-zinc-800 px-4 py-2.5 flex items-center justify-between text-xs text-zinc-700 dark:text-zinc-300 z-50 transition-colors">
        <div class="flex items-center gap-2.5">
            <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse shrink-0"></span>
            <span><strong>Kunci API Belum Diisi:</strong> Masukkan kunci API di menu Pengaturan agar AI dapat membantu Anda merancang proyek.</span>
        </div>
        <a href="{{ url('/settings') }}" class="px-3 py-1 bg-black text-white hover:bg-zinc-800 dark:bg-white dark:text-black dark:hover:bg-zinc-200 font-semibold rounded-md text-xs transition-colors shrink-0 shadow-xs">
            Buka Pengaturan &rarr;
        </a>
    </div>

    <!-- 2. Main Shell Layout: Sidebar + Content -->
    <div class="flex-1 flex overflow-hidden">
        
        <!-- Sidebar Wrapper: maintains layout stability in Supabase hover mode -->
        <div id="sidebar-wrapper" class="sidebar-container">
            <!-- Sidebar Navigasi (Hover-expandable like Supabase) -->
            <aside id="app-sidebar" class="bg-white dark:bg-[#0c0c0e] border-r border-zinc-200 dark:border-zinc-800 flex flex-col select-none transition-colors duration-200">
                
                <!-- Sidebar Header: Brand & Pin Toggle -->
                <div class="h-16 px-3 flex items-center justify-between border-b border-zinc-200 dark:border-zinc-800 shrink-0 sidebar-center-on-collapse">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-lg bg-black text-white dark:bg-white dark:text-black flex items-center justify-center font-bold font-mono text-xs shadow-xs shrink-0 transition-colors">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                            </svg>
                        </div>
                        <div class="sidebar-expandable brand-name flex flex-col leading-tight min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="font-bold text-sm tracking-tight text-zinc-900 dark:text-white truncate">DEVArchitect</span>
                                <span class="text-[10px] px-1.5 py-0.5 bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800 rounded-full font-semibold shrink-0">v2.0</span>
                            </div>
                            <span class="text-[10px] text-zinc-400 dark:text-zinc-500 truncate">Schema Engine</span>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Navigation Links (with Supabase-style rounded icon boxes) -->
                <nav class="flex-1 px-2.5 py-4 space-y-1.5 overflow-y-auto">
                    @php
                        $isAssistant = request()->is('assistant') || request()->is('/');
                        $isDashboard = request()->is('dashboard');
                        $isGenerator = request()->is('generator') || request()->is('generations*');
                        $isHistory = request()->is('history');
                        $isSettings = request()->is('settings');
                    @endphp

                    <!-- 1. Asisten Dokumen (Top Primary Workspace) -->
                    <a href="{{ url('/assistant') }}" title="Asisten Dokumen"
                       class="sidebar-center-on-collapse group flex items-center gap-3 px-2.5 py-1.5 rounded-lg text-xs font-medium transition-all {{ $isAssistant ? 'bg-zinc-100/90 dark:bg-zinc-800/80 text-zinc-900 dark:text-white font-semibold' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100/70 dark:text-zinc-400 dark:hover:text-zinc-100 dark:hover:bg-zinc-800/40' }}">
                        <div class="sidebar-icon-box w-9 h-9 rounded-lg flex items-center justify-center shrink-0 transition-all {{ $isAssistant ? 'bg-emerald-500/15 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400 shadow-xs' : 'text-zinc-500 dark:text-zinc-400 group-hover:text-zinc-900 dark:group-hover:text-zinc-100 group-hover:bg-zinc-200/60 dark:group-hover:bg-zinc-700/60' }}">
                            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                            </svg>
                        </div>
                        <span class="sidebar-expandable flex-1 truncate font-medium {{ $isAssistant ? 'text-zinc-900 dark:text-white font-semibold' : 'text-zinc-600 dark:text-zinc-400 group-hover:text-zinc-900 dark:group-hover:text-white' }}">
                            Asisten Dokumen
                        </span>
                        @if($isAssistant)
                            <span class="sidebar-expandable w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0 mr-1"></span>
                        @endif
                    </a>

                    <!-- 2. Daftar Proyek -->
                    <a href="{{ url('/dashboard') }}" title="Daftar Proyek"
                       class="sidebar-center-on-collapse group flex items-center gap-3 px-2.5 py-1.5 rounded-lg text-xs font-medium transition-all {{ $isDashboard ? 'bg-zinc-100/90 dark:bg-zinc-800/80 text-zinc-900 dark:text-white font-semibold' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100/70 dark:text-zinc-400 dark:hover:text-zinc-100 dark:hover:bg-zinc-800/40' }}">
                        <div class="sidebar-icon-box w-9 h-9 rounded-lg flex items-center justify-center shrink-0 transition-all {{ $isDashboard ? 'bg-emerald-500/15 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400 shadow-xs' : 'text-zinc-500 dark:text-zinc-400 group-hover:text-zinc-900 dark:group-hover:text-zinc-100 group-hover:bg-zinc-200/60 dark:group-hover:bg-zinc-700/60' }}">
                            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                            </svg>
                        </div>
                        <span class="sidebar-expandable flex-1 truncate font-medium {{ $isDashboard ? 'text-zinc-900 dark:text-white font-semibold' : 'text-zinc-600 dark:text-zinc-400 group-hover:text-zinc-900 dark:group-hover:text-white' }}">
                            Daftar Proyek
                        </span>
                        @if($isDashboard)
                            <span class="sidebar-expandable w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0 mr-1"></span>
                        @endif
                    </a>

                    <!-- 3. Pembuat Database -->
                    <a href="{{ url('/generator') }}" title="Pembuat Database"
                       class="sidebar-center-on-collapse group flex items-center gap-3 px-2.5 py-1.5 rounded-lg text-xs font-medium transition-all {{ $isGenerator ? 'bg-zinc-100/90 dark:bg-zinc-800/80 text-zinc-900 dark:text-white font-semibold' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100/70 dark:text-zinc-400 dark:hover:text-zinc-100 dark:hover:bg-zinc-800/40' }}">
                        <div class="sidebar-icon-box w-9 h-9 rounded-lg flex items-center justify-center shrink-0 transition-all {{ $isGenerator ? 'bg-emerald-500/15 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400 shadow-xs' : 'text-zinc-500 dark:text-zinc-400 group-hover:text-zinc-900 dark:group-hover:text-zinc-100 group-hover:bg-zinc-200/60 dark:group-hover:bg-zinc-700/60' }}">
                            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <span class="sidebar-expandable flex-1 truncate font-medium {{ $isGenerator ? 'text-zinc-900 dark:text-white font-semibold' : 'text-zinc-600 dark:text-zinc-400 group-hover:text-zinc-900 dark:group-hover:text-white' }}">
                            Pembuat Database
                        </span>
                        @if($isGenerator)
                            <span class="sidebar-expandable w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0 mr-1"></span>
                        @endif
                    </a>

                    <!-- 4. Riwayat Pembuatan -->
                    <a href="{{ url('/history') }}" title="Riwayat Pembuatan"
                       class="sidebar-center-on-collapse group flex items-center gap-3 px-2.5 py-1.5 rounded-lg text-xs font-medium transition-all {{ $isHistory ? 'bg-zinc-100/90 dark:bg-zinc-800/80 text-zinc-900 dark:text-white font-semibold' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100/70 dark:text-zinc-400 dark:hover:text-zinc-100 dark:hover:bg-zinc-800/40' }}">
                        <div class="sidebar-icon-box w-9 h-9 rounded-lg flex items-center justify-center shrink-0 transition-all {{ $isHistory ? 'bg-emerald-500/15 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400 shadow-xs' : 'text-zinc-500 dark:text-zinc-400 group-hover:text-zinc-900 dark:group-hover:text-zinc-100 group-hover:bg-zinc-200/60 dark:group-hover:bg-zinc-700/60' }}">
                            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <span class="sidebar-expandable flex-1 truncate font-medium {{ $isHistory ? 'text-zinc-900 dark:text-white font-semibold' : 'text-zinc-600 dark:text-zinc-400 group-hover:text-zinc-900 dark:group-hover:text-white' }}">
                            Riwayat Pembuatan
                        </span>
                        @if($isHistory)
                            <span class="sidebar-expandable w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0 mr-1"></span>
                        @endif
                    </a>

                    <!-- 5. Pengaturan -->
                    <a href="{{ url('/settings') }}" title="Pengaturan"
                       class="sidebar-center-on-collapse group flex items-center gap-3 px-2.5 py-1.5 rounded-lg text-xs font-medium transition-all {{ $isSettings ? 'bg-zinc-100/90 dark:bg-zinc-800/80 text-zinc-900 dark:text-white font-semibold' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100/70 dark:text-zinc-400 dark:hover:text-zinc-100 dark:hover:bg-zinc-800/40' }}">
                        <div class="sidebar-icon-box w-9 h-9 rounded-lg flex items-center justify-center shrink-0 transition-all {{ $isSettings ? 'bg-emerald-500/15 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400 shadow-xs' : 'text-zinc-500 dark:text-zinc-400 group-hover:text-zinc-900 dark:group-hover:text-zinc-100 group-hover:bg-zinc-200/60 dark:group-hover:bg-zinc-700/60' }}">
                            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                        <span class="sidebar-expandable flex-1 truncate font-medium {{ $isSettings ? 'text-zinc-900 dark:text-white font-semibold' : 'text-zinc-600 dark:text-zinc-400 group-hover:text-zinc-900 dark:group-hover:text-white' }}">
                            Pengaturan
                        </span>
                        @if($isSettings)
                            <span class="sidebar-expandable w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0 mr-1"></span>
                        @endif
                    </a>
                </nav>



                <!-- User Profile Footer Bar -->
                <div class="h-16 px-3 border-t border-zinc-200 dark:border-zinc-800 flex items-center justify-between bg-white dark:bg-[#0c0c0e] transition-colors shrink-0 sidebar-center-on-collapse">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-9 h-9 rounded-full bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center text-zinc-700 dark:text-zinc-300 font-semibold text-xs shrink-0 shadow-xs">
                            IA
                        </div>
                        <div class="sidebar-expandable flex flex-col leading-tight truncate">
                            <span class="font-semibold text-xs text-zinc-900 dark:text-white truncate">Indra Agustin</span>
                            <span class="text-[10px] text-zinc-400 truncate">indra@example.com</span>
                        </div>
                    </div>
                    <button type="button" class="sidebar-expandable text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 p-1 shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                </div>
            </aside>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col overflow-hidden bg-[#fafafa] dark:bg-black transition-colors duration-200">
            
            <!-- Topbar (h-16 matching sidebar header seamlessly) -->
            <header class="h-16 border-b border-zinc-200 dark:border-zinc-800 px-6 flex items-center justify-between bg-white dark:bg-[#0c0c0e] shrink-0 transition-colors duration-200">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex items-center gap-2">
                        <h1 class="text-base font-bold text-zinc-900 dark:text-white tracking-tight">{{ $headerTitle ?? 'Dashboard' }}</h1>
                        @if(isset($headerSubtitle))
                            <span class="text-zinc-300 dark:text-zinc-700 font-light">/</span>
                            <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 truncate">{{ $headerSubtitle }}</span>
                        @endif
                    </div>
                    <span class="hidden md:inline-block text-zinc-300 dark:text-zinc-700">|</span>
                    <p class="hidden md:block text-xs text-zinc-400 dark:text-zinc-500 truncate">Kelola proyek dan struktur database aplikasi Anda dalam satu tempat.</p>
                </div>

                <div class="flex items-center gap-2.5 shrink-0">
                    @yield('topbar-actions')
                </div>
            </header>

            <!-- Scrollable Page Content -->
            <main class="flex-1 overflow-y-auto {{ $mainClass ?? 'p-8' }} bg-[#fafafa] dark:bg-black transition-colors duration-200">
                @yield('content')
            </main>
        </div>
    </div>

    <!-- 3. Radix Toast Container -->
    <div id="toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none *:pointer-events-auto"></div>

    @stack('scripts')
</body>
</html>
