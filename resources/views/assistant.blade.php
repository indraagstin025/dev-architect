@extends('layouts.app')

@php
    $headerTitle = 'Asisten Dokumen AI';
    $headerSubtitle = 'Rancang Konsep & Kebutuhan Aplikasi';
    $mainClass = 'p-0 overflow-hidden flex flex-col h-full';
@endphp

@section('content')
<div class="flex-1 flex flex-col h-full overflow-hidden bg-zinc-50 dark:bg-[#070709] text-zinc-900 dark:text-zinc-100 select-text">

    <!-- Top Subbar: Project Selector, Centered Stage Pipeline Stepper, Canvas Toggle -->
    <div class="h-14 border-b border-zinc-200/90 dark:border-zinc-800/80 px-3 sm:px-5 flex items-center justify-between gap-2 relative bg-white/90 dark:bg-[#0c0c0e]/90 backdrop-blur-md shrink-0 z-20">
        
        <!-- Left: Project Selector & Minimalist New Project Button -->
        <div class="flex items-center gap-1.5 shrink-0 min-w-0 z-20">
            <div class="relative w-40 sm:w-48 md:w-52 max-w-[210px]">
                <select id="doc-project-selector" onchange="selectDocProject(this.value)"
                        class="w-full bg-zinc-100/90 dark:bg-zinc-900/90 border border-zinc-200/80 dark:border-zinc-800 rounded-xl pl-3 pr-7 py-1.5 text-xs font-medium text-zinc-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-emerald-500 truncate cursor-pointer transition-colors shadow-2xs">
                    <option value="">— Pilih atau buat proyek —</option>
                </select>
                <svg class="w-3.5 h-3.5 pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </div>

            <!-- Minimalist New Project Icon Button with Tooltip -->
            <button type="button" onclick="openNewDocModal()" 
                    class="w-8 h-8 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-zinc-100 dark:hover:bg-zinc-800 flex items-center justify-center shrink-0 transition-all shadow-2xs active:scale-95 z-20"
                    title="Buat Proyek Dokumen Baru">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4v16m8-8H4" />
                </svg>
            </button>
        </div>

        <!-- Center: Interactive Stage Stepper Pipeline (Flex-Centered, Collision-Proof) -->
        <div class="hidden md:flex flex-1 items-center justify-center min-w-0 px-2 z-10">
            <div id="stage-pipeline" class="flex items-center gap-1 lg:gap-1.5 px-3 py-1 rounded-full bg-zinc-100/90 dark:bg-zinc-900/70 border border-zinc-200/80 dark:border-zinc-800/80 flex-nowrap shadow-2xs overflow-x-auto no-scrollbar">
                <!-- Rendered by doc-assistant.js -> renderStagePipeline() -->
            </div>
        </div>

        <!-- Right: Minimalist Canvas Toggle & Quick Actions -->
        <div class="flex items-center gap-1.5 shrink-0 z-20">
            <!-- Minimalist Canvas Toggle Icon Button with Badge & Tooltip -->
            <button type="button" id="btn-toggle-canvas" onclick="toggleCanvas()"
                    class="relative w-8 h-8 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-zinc-600 dark:text-zinc-400 hover:text-emerald-500 dark:hover:text-emerald-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 flex items-center justify-center shadow-xs transition-all active:scale-95 shrink-0"
                    title="Buka / Tutup Lembar Dokumen (Canvas)">
                <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span id="canvas-version-badge" class="absolute -top-1 -right-1 px-1 min-w-[15px] h-3.5 rounded-full text-[9px] font-mono font-bold bg-emerald-500 text-white flex items-center justify-center shadow-xs">0</span>
            </button>

            <!-- Project Actions Dropdown Trigger -->
            <div class="relative shrink-0">
                <button type="button" onclick="toggleProjectMenu()" id="btn-project-menu"
                        class="w-8 h-8 rounded-xl border border-zinc-200 dark:border-zinc-800 text-zinc-500 hover:text-zinc-900 dark:hover:text-white hover:bg-zinc-100 dark:hover:bg-zinc-800 flex items-center justify-center transition-colors shadow-2xs"
                        title="Menu Opsi Proyek">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                    </svg>
                </button>
                <div id="project-menu-pop" class="hidden absolute right-0 mt-1.5 w-48 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-xl p-1.5 space-y-1 z-30">
                    <button type="button" onclick="archiveDocProject()" class="w-full px-2.5 py-1.5 text-left text-xs rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 flex items-center gap-2 text-zinc-700 dark:text-zinc-300 transition-colors">
                        <svg class="w-3.5 h-3.5 text-zinc-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                        </svg>
                        <span id="menu-archive-text">Arsipkan Dokumen</span>
                    </button>
                    <button type="button" onclick="deleteDocProject()" class="w-full px-2.5 py-1.5 text-left text-xs rounded-lg hover:bg-red-50 dark:hover:bg-red-950/30 text-red-600 dark:text-red-400 flex items-center gap-2 transition-colors">
                        <svg class="w-3.5 h-3.5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <span>Hapus Dokumen</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Studio Split View: Chat Centered + Canvas Slide-In Right -->
    <div id="studio-split" class="flex-1 flex overflow-hidden relative">

        <!-- ================= LEFT: CHAT PANE (Claude / ChatGPT centered style) ================= -->
        <div id="chat-pane" class="flex-1 flex flex-col h-full overflow-hidden relative" style="transition: all 0.35s cubic-bezier(0.4,0,0.2,1);">
            
            <!-- Chat Scroll Area — centered like Claude/Gemini -->
            <div id="chat-scroll-container" class="flex-1 overflow-y-auto py-4 scroll-smooth select-text flex flex-col items-center">
                <div id="chat-inner" class="w-full max-w-4xl px-4 sm:px-6 mx-auto flex-1 flex flex-col transition-all duration-300">

                    <!-- Mobile Stage Stepper Bar (Only shown on small screens) -->
                    <div id="stage-pipeline-mobile" class="md:hidden flex items-center gap-1 px-3 py-1.5 rounded-xl bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 text-[11px] overflow-x-auto no-scrollbar mb-3"></div>

                    <!-- Gemini Signature Hero Landing (Empty State: Perfectly Centered in Viewport) -->
                    <div id="chat-empty" class="my-auto py-6 flex flex-col items-center justify-center text-center space-y-6">
                        <!-- Icon Orb with Soft Emerald/Blue Gradient Glow -->
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-emerald-500/20 via-blue-500/20 to-indigo-500/20 border border-emerald-500/30 flex items-center justify-center shadow-lg">
                            <svg class="w-8 h-8 text-emerald-500 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"/>
                            </svg>
                        </div>

                        <!-- Gradient Greeting Title -->
                        <div class="space-y-2 max-w-lg">
                            <h2 class="text-2xl sm:text-3xl font-bold tracking-tight bg-gradient-to-r from-zinc-900 via-zinc-700 to-zinc-500 dark:from-white dark:via-zinc-200 dark:to-zinc-400 bg-clip-text text-transparent">
                                Ada ide aplikasi yang ingin Anda rancang?
                            </h2>
                            <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 leading-relaxed">
                                Asisten AI akan memandu Anda menyusun konsep aplikasi langkah demi langkah, mulai dari ide awal, daftar kebutuhan, hingga siap diubah menjadi database.
                            </p>
                        </div>

                        <!-- 4 Suggestion Prompt Cards (Gemini Style) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 w-full max-w-2xl text-left pt-2">
                            <button type="button" onclick="useBriefTemplate(0)"
                                    class="group p-4 rounded-2xl border border-zinc-200/90 dark:border-zinc-800/90 bg-white/70 dark:bg-zinc-900/40 hover:bg-white dark:hover:bg-zinc-900 hover:border-emerald-500/40 dark:hover:border-emerald-500/40 transition-all shadow-2xs hover:shadow-md cursor-pointer space-y-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-lg bg-emerald-500/10 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-500/20 group-hover:scale-105 transition-transform">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                        </svg>
                                    </div>
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-white group-hover:text-emerald-500 transition-colors">Marketplace & Toko Online</span>
                                </div>
                                <p class="text-[11px] text-zinc-500 dark:text-zinc-400 leading-relaxed">Penjual, pembeli, katalog varian barang, keranjang belanja, escrow payment, dan ulasan.</p>
                            </button>

                            <button type="button" onclick="useBriefTemplate(1)"
                                    class="group p-4 rounded-2xl border border-zinc-200/90 dark:border-zinc-800/90 bg-white/70 dark:bg-zinc-900/40 hover:bg-white dark:hover:bg-zinc-900 hover:border-emerald-500/40 dark:hover:border-emerald-500/40 transition-all shadow-2xs hover:shadow-md cursor-pointer space-y-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-lg bg-blue-500/10 dark:bg-blue-500/15 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0 border border-blue-500/20 group-hover:scale-105 transition-transform">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                    </div>
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-white group-hover:text-emerald-500 transition-colors">Manajemen Inventaris & Gudang</span>
                                </div>
                                <p class="text-[11px] text-zinc-500 dark:text-zinc-400 leading-relaxed">Multi-warehouse, stok masuk/keluar, scan barcode, opname berkala, dan notifikasi limit.</p>
                            </button>

                            <button type="button" onclick="useBriefTemplate(2)"
                                    class="group p-4 rounded-2xl border border-zinc-200/90 dark:border-zinc-800/90 bg-white/70 dark:bg-zinc-900/40 hover:bg-white dark:hover:bg-zinc-900 hover:border-emerald-500/40 dark:hover:border-emerald-500/40 transition-all shadow-2xs hover:shadow-md cursor-pointer space-y-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-lg bg-indigo-500/10 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 border border-indigo-500/20 group-hover:scale-105 transition-transform">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                    </div>
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-white group-hover:text-emerald-500 transition-colors">HRIS & Presensi Karyawan</span>
                                </div>
                                <p class="text-[11px] text-zinc-500 dark:text-zinc-400 leading-relaxed">Absensi GPS & QR code, shift kerja, pengajuan cuti/lembur, serta rekapitulasi kehadiran untuk payroll bulanan.</p>
                            </button>

                            <button type="button" onclick="useBriefTemplate(3)"
                                    class="group p-4 rounded-2xl border border-zinc-200/90 dark:border-zinc-800/90 bg-white/70 dark:bg-zinc-900/40 hover:bg-white dark:hover:bg-zinc-900 hover:border-emerald-500/40 dark:hover:border-emerald-500/40 transition-all shadow-2xs hover:shadow-md cursor-pointer space-y-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-lg bg-violet-500/10 dark:bg-violet-500/15 text-violet-600 dark:text-violet-400 flex items-center justify-center shrink-0 border border-violet-500/20 group-hover:scale-105 transition-transform">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-white group-hover:text-emerald-500 transition-colors">Reservasi Layanan & Booking</span>
                                </div>
                                <p class="text-[11px] text-zinc-500 dark:text-zinc-400 leading-relaxed">Slot waktu praktisi/dokter, kuota antrean harian, reminder konfirmasi, dan deposit tiket.</p>
                            </button>
                        </div>
                    </div>

                    <!-- Message Feed (Shown when project has messages) -->
                    <div id="chat-messages" class="hidden space-y-6 pb-6 w-full">
                        <!-- Message Bubbles Rendered by doc-assistant.js -> renderMessages() -->
                    </div>
                </div>
            </div>

            <!-- Floating Pill Composer Area — centered like Claude/ChatGPT -->
            <div class="pb-5 pt-2 bg-gradient-to-t from-zinc-50 via-zinc-50/95 dark:from-[#070709] dark:via-[#070709]/95 to-transparent shrink-0 z-10 flex flex-col items-center">
                <div id="composer-inner" class="w-full max-w-4xl px-4 sm:px-6 mx-auto relative transition-all duration-300">

                    <!-- Generating / Processing Indicator Pill -->
                    <div id="chat-busy" class="hidden items-center justify-between px-3.5 py-1.5 mb-2 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-xs text-emerald-600 dark:text-emerald-400 shadow-xs backdrop-blur-md">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full border-2 border-emerald-500/40 border-t-emerald-500 animate-spin"></span>
                            <span id="chat-busy-text">Asisten AI sedang menyusun dokumen kebutuhan Anda...</span>
                        </div>
                        <button type="button" onclick="cancelAssistant()" class="text-[11px] font-semibold text-red-500 hover:text-red-600 hover:underline ml-3">
                            Batalkan
                        </button>
                    </div>

                    <!-- Quick Prompt Chips (Chips above composer) -->
                    <div id="quick-prompt-chips" class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pb-2 text-[11px] text-zinc-600 dark:text-zinc-400">
                        <span class="text-zinc-400 text-[10px] uppercase font-semibold mr-0.5 shrink-0 select-none">Pintasan:</span>
                        <button type="button" onclick="sendQuickPrompt('Lanjutkan ke seksi berikutnya secara rinci.')" class="px-2.5 py-1 rounded-full border border-zinc-200 dark:border-zinc-800 bg-white/80 dark:bg-zinc-900/70 hover:border-emerald-500/40 hover:text-emerald-500 shrink-0 transition-colors shadow-2xs flex items-center gap-1.5">
                            <svg class="w-3 h-3 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <span>Lanjut ke Bagian Berikutnya</span>
                        </button>
                        <button type="button" onclick="sendQuickPrompt('Lengkapi tabel analisis risiko beserta mitigasi dan kompleksitasnya.')" class="px-2.5 py-1 rounded-full border border-zinc-200 dark:border-zinc-800 bg-white/80 dark:bg-zinc-900/70 hover:border-emerald-500/40 hover:text-emerald-500 shrink-0 transition-colors shadow-2xs flex items-center gap-1.5">
                            <svg class="w-3 h-3 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                            <span>Analisis Risiko</span>
                        </button>
                        <button type="button" onclick="sendQuickPrompt('Rancang diagram modul arsitektur dan relasi entitas basis datanya.')" class="px-2.5 py-1 rounded-full border border-zinc-200 dark:border-zinc-800 bg-white/80 dark:bg-zinc-900/70 hover:border-emerald-500/40 hover:text-emerald-500 shrink-0 transition-colors shadow-2xs flex items-center gap-1.5">
                            <svg class="w-3 h-3 text-purple-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7c0-2-1.5-3-3.5-3h-9C5.5 4 4 5 4 7zm0 5h16M10 4v16" />
                            </svg>
                            <span>Rancang Struktur Tabel</span>
                        </button>
                        <button type="button" onclick="sendQuickPrompt('Ringkas kembali poin-poin yang disepakati untuk siap di-approve.')" class="px-2.5 py-1 rounded-full border border-zinc-200 dark:border-zinc-800 bg-white/80 dark:bg-zinc-900/70 hover:border-emerald-500/40 hover:text-emerald-500 shrink-0 transition-colors shadow-2xs flex items-center gap-1.5">
                            <svg class="w-3 h-3 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Ringkas untuk Disetujui</span>
                        </button>
                    </div>

                    <!-- Floating Pill Composer Form -->
                    <form onsubmit="sendChat(event)" class="relative rounded-3xl border border-zinc-300/80 dark:border-zinc-700/70 bg-white dark:bg-zinc-900/95 shadow-xl shadow-zinc-200/50 dark:shadow-black/60 focus-within:border-emerald-500 dark:focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-500/20 transition-all p-2 sm:p-2.5">
                        
                        <!-- Auto-expanding Textarea (Clean, overflow-hidden by default to prevent vertical scrollbar thumb) -->
                        <textarea id="chat-input" rows="1" maxlength="8000"
                                  placeholder="Tuliskan ide aplikasi, fitur yang diinginkan, atau pertanyaan Anda... (Enter kirim, Shift+Enter baris baru)"
                                  onkeydown="handleChatKeydown(event)"
                                  oninput="autoExpandTextarea(this)"
                                  class="w-full bg-transparent border-0 outline-none text-xs sm:text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 dark:placeholder-zinc-500 resize-none max-h-40 px-3 py-1.5 focus:ring-0 leading-relaxed overflow-hidden"></textarea>

                        <!-- Bottom Composer Toolbar -->
                        <div class="flex items-center justify-between pt-1.5 px-1 border-t border-zinc-100 dark:border-zinc-800/60 mt-1">
                            
                            <!-- Left: Model Pill Selector with max-w to prevent pushing buttons -->
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="relative max-w-[140px] sm:max-w-[180px] md:max-w-[220px]">
                                    <select id="doc-model-composer" title="Pilih Model AI"
                                            class="w-full appearance-none bg-zinc-100 dark:bg-zinc-800/80 hover:bg-zinc-200 dark:hover:bg-zinc-700/80 text-zinc-800 dark:text-zinc-200 border border-zinc-200 dark:border-zinc-700/60 rounded-full pl-3 pr-7 py-1 text-xs font-sans font-medium cursor-pointer transition-colors focus:outline-none truncate">
                                    </select>
                                    <svg class="w-3.5 h-3.5 pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>

                                <button type="button" onclick="openSnapshotModal()" title="Simpan balasan ini ke Lembar Dokumen (Canvas)"
                                        class="px-2.5 py-1 rounded-full text-[11px] border border-zinc-200 dark:border-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-600 dark:text-zinc-400 flex items-center gap-1.5 transition-colors shrink-0">
                                    <svg class="w-3.5 h-3.5 text-zinc-500 dark:text-zinc-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                                    </svg>
                                    <span class="hidden sm:inline">Simpan ke Lembar Dokumen</span>
                                </button>
                            </div>

                            <!-- Right: Send Button (Circle with Arrow Up like ChatGPT) -->
                            <button type="submit" id="btn-chat-send"
                                    class="w-8 h-8 rounded-full bg-zinc-900 hover:bg-black text-white dark:bg-white dark:hover:bg-zinc-200 dark:text-black flex items-center justify-center transition-all disabled:opacity-30 disabled:cursor-not-allowed shadow-xs shrink-0 active:scale-95"
                                    title="Kirim Pesan (Enter)">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                                </svg>
                            </button>
                        </div>
                    </form>

                    <!-- Bottom Micro Disclaimer -->
                    <p class="text-[10px] text-center text-zinc-400 dark:text-zinc-500 mt-2 tracking-tight">
                        AI bisa saja membuat kekeliruan. Pastikan untuk selalu memeriksa kembali hasil draf di lembar dokumen.
                    </p>
                </div>
            </div>
        </div>

        <!-- ================= RIGHT: DOCUMENT CANVAS PARTIAL ================= -->
        @include('assistant.partials.canvas-panel')

    </div>
</div>

<!-- ================= MODALS PARTIAL ================= -->
@include('assistant.partials.modals')

@endsection

@push('scripts')
@vite(['resources/js/searchable-select.js', 'resources/js/doc-assistant.js'])
@endpush
