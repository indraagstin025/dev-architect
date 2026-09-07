<!-- Document Canvas Panel (ChatGPT Canvas / Artifacts style) -->
<aside id="canvas-panel" class="border-l border-zinc-200 dark:border-zinc-800 bg-white dark:bg-[#0c0c0e] flex flex-col shrink-0 z-20 shadow-2xl">
    
    <!-- Canvas Header -->
    <div class="h-14 px-4 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between bg-zinc-50/50 dark:bg-black/20 shrink-0">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-7 h-7 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 flex items-center justify-center shrink-0 border border-zinc-200 dark:border-zinc-700/60">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <div class="leading-tight truncate">
                <div class="flex items-center gap-1.5">
                    <span class="font-bold text-xs text-zinc-900 dark:text-white font-mono uppercase tracking-wider" id="canvas-doc-type">URD</span>
                    <span class="text-[10px] font-mono px-1.5 py-0.5 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-semibold" id="canvas-version-tag">v1</span>
                    <span class="text-[10px] font-mono px-1.5 py-0.5 rounded-full border border-zinc-200 dark:border-zinc-700 text-zinc-500 uppercase" id="canvas-status-tag">draft</span>
                </div>
                <p class="text-[10px] text-zinc-400 truncate" id="canvas-doc-subtitle">Lembar Dokumen</p>
            </div>
        </div>

        <div class="flex items-center gap-1.5">
            <!-- Version Selector Dropdown inside Canvas -->
            <select id="canvas-version-picker" onchange="switchCanvasVersion(this.value)"
                    class="bg-zinc-100 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700/60 rounded-lg px-2 py-1 text-xs font-mono text-zinc-700 dark:text-zinc-300 focus:outline-none">
                <option value="">(Belum ada draf)</option>
            </select>

            <!-- Close Canvas Button -->
            <button type="button" onclick="toggleCanvas(false)" class="p-1 rounded-lg text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800" title="Tutup Lembar Dokumen">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Canvas Tabs: Minimalist Segmented Icon Bar with Tooltips (Cursor/Notion Style) -->
    <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-800 px-4 py-2 bg-zinc-50/50 dark:bg-black/20 text-xs shrink-0">
        <!-- Segmented Icon Buttons -->
        <div class="flex items-center gap-1 bg-zinc-200/60 dark:bg-zinc-800/60 p-1 rounded-xl border border-zinc-200/60 dark:border-zinc-700/50 shadow-2xs">
            <button type="button" onclick="setCanvasTab('preview')" id="tab-btn-preview"
                    class="w-7 h-7 rounded-lg text-emerald-600 dark:text-emerald-400 bg-white dark:bg-zinc-900 shadow-2xs transition-all flex items-center justify-center active:scale-95"
                    title="Lihat Hasil Dokumen (Preview)">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
            </button>
            <button type="button" onclick="setCanvasTab('edit')" id="tab-btn-edit"
                    class="w-7 h-7 rounded-lg text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 transition-all flex items-center justify-center active:scale-95"
                    title="Edit Teks Dokumen">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </button>
            <button type="button" onclick="setCanvasTab('diff')" id="tab-btn-diff"
                    class="w-7 h-7 rounded-lg text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 transition-all flex items-center justify-center active:scale-95"
                    title="Bandingkan Perubahan Versi">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                </svg>
            </button>
        </div>

        <!-- Setting Icon Tooltip Button -->
        <button type="button" onclick="setCanvasTab('config')" id="tab-btn-config"
                class="w-8 h-8 rounded-xl border border-zinc-200/80 dark:border-zinc-800 text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 flex items-center justify-center transition-all shadow-2xs active:scale-95"
                title="Pengaturan Model & Hubungkan Proyek">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
        </button>
    </div>

    <!-- Canvas Content Area -->
    <div class="flex-1 overflow-y-auto p-4 space-y-4 select-text">
        
        <!-- TAB 1: PREVIEW (Rendered Markdown) -->
        <div id="canvas-tab-preview" class="space-y-3">
            <div id="canvas-preview-empty" class="text-center py-12 text-zinc-400 text-xs italic">
                Belum ada draf dokumen yang dipilih.<br>Kirim pesan ke Asisten AI atau klik "Simpan ke Lembar Dokumen" untuk melihat draf di sini.
            </div>
            <div id="canvas-preview-content" class="hidden prose dark:prose-invert prose-xs max-w-none text-xs leading-relaxed space-y-3">
                <!-- Rendered Markdown Output -->
            </div>
        </div>

        <!-- TAB 2: EDITOR (Direct Markdown Textarea) -->
        <div id="canvas-tab-edit" class="hidden space-y-3 h-full flex flex-col">
            <p class="text-[11px] text-zinc-500">Edit teks dokumen secara langsung di bawah ini. Versi yang berstatus draf dapat Anda sesuaikan kapan saja.</p>
            <textarea id="canvas-editor-textarea" rows="18" maxlength="200000"
                      placeholder="Tulis atau perbaiki isi dokumen di sini..."
                      class="w-full flex-1 bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-xl p-3 text-xs font-mono leading-relaxed text-zinc-900 dark:text-zinc-100 focus:ring-1 focus:ring-emerald-500 focus:outline-none resize-none"></textarea>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="saveCanvasEdit()" id="btn-save-canvas-edit"
                        class="px-4 py-1.5 bg-black text-white dark:bg-white dark:text-black rounded-lg text-xs font-semibold shadow-xs">
                    Simpan Perubahan
                </button>
            </div>
        </div>

        <!-- TAB 3: DIFF (TASK-1005: Line Diff v1 vs v2) -->
        <div id="canvas-tab-diff" class="hidden space-y-3">
            <div class="flex items-center justify-between text-xs pb-2 border-b border-zinc-200 dark:border-zinc-800">
                <span class="font-semibold text-zinc-700 dark:text-zinc-300">Bandingkan Versi:</span>
                <div class="flex items-center gap-1.5">
                    <select id="diff-version-old" onchange="renderDiffView()" class="bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded px-2 py-1 text-[11px] font-mono"></select>
                    <svg class="w-3.5 h-3.5 text-zinc-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                    <select id="diff-version-new" onchange="renderDiffView()" class="bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded px-2 py-1 text-[11px] font-mono"></select>
                </div>
            </div>
            <div id="diff-summary-stats" class="text-[11px] font-mono text-zinc-500 flex items-center gap-3"></div>
            <div id="diff-output-box" class="border border-zinc-200 dark:border-zinc-800 rounded-xl p-3 bg-zinc-50 dark:bg-zinc-950 font-mono text-[11px] max-h-[500px] overflow-y-auto space-y-0.5"></div>
        </div>

        <!-- TAB 4: CONFIG & PROYEK KODE -->
        <div id="canvas-tab-config" class="hidden space-y-4">
            
            <!-- AI Model Config Card -->
            <div class="border border-zinc-200 dark:border-zinc-800 rounded-xl p-3.5 space-y-2.5 bg-zinc-50/50 dark:bg-zinc-900/30">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-500">Model AI Proyek</h4>
                    <button type="button" onclick="refreshCatalog(true)" class="text-xs text-zinc-400 hover:text-zinc-200 flex items-center gap-1 transition-colors" title="Muat Ulang Katalog Model AI">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>Refresh</span>
                    </button>
                </div>
                <select id="doc-model" onchange="changeDocModel(this.value)"
                        class="w-full bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-lg px-2.5 py-1.5 text-xs font-sans font-medium"></select>
                
                <div class="flex gap-1.5">
                    <input type="text" id="doc-model-custom" maxlength="100" placeholder="custom/model:id"
                           class="flex-1 min-w-0 bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-lg px-2.5 py-1.5 text-xs font-sans font-medium">
                    <button type="button" onclick="useCustomModel()" class="px-3 py-1.5 border border-zinc-200 dark:border-zinc-800 rounded-lg text-xs font-semibold">Terapkan</button>
                </div>
                <p id="model-meta" class="text-[10px] text-zinc-500 font-sans"></p>
            </div>

            <!-- Linked Code Project Card -->
            <div class="border border-zinc-200 dark:border-zinc-800 rounded-xl p-3.5 space-y-2 bg-zinc-50/50 dark:bg-zinc-900/30">
                <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-500">Hubungkan ke Folder Proyek</h4>
                <select id="link-code-project" onchange="linkCodeProject(this.value)"
                        class="w-full bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-lg px-2.5 py-1.5 text-xs">
                    <option value="">— Belum terhubung —</option>
                </select>
                <p class="text-[10px] text-zinc-500">Menghubungkan dokumen ini dengan proyek Anda agar rancangan database bisa langsung dibuatkan otomatis.</p>
            </div>

            <!-- Auto-Generate Section Box -->
            <div class="border border-zinc-200 dark:border-zinc-800 rounded-xl p-3.5 space-y-2.5 bg-zinc-50/50 dark:bg-zinc-900/30">
                <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-500">Tulis Otomatis per Bab</h4>
                <div class="grid grid-cols-[1fr_1fr_auto] gap-1.5">
                    <select id="gen-doc-type" onchange="loadSections()" class="bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-lg px-2 py-1.5 text-xs">
                        <option value="urd">URD (Kebutuhan)</option>
                        <option value="prd">PRD (Fitur)</option>
                        <option value="srs">SRS (Spesifikasi)</option>
                        <option value="sysdesign">Desain Sistem</option>
                    </select>
                    <select id="gen-section" class="bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-lg px-2 py-1.5 text-xs">
                        <option value="">Seluruh Dokumen</option>
                    </select>
                    <button type="button" onclick="generateDoc()" title="Tulis Otomatis via AI"
                            class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-semibold shadow-xs flex items-center gap-1.5 transition-colors">
                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <span>Tulis</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Canvas Bottom Action Toolbar -->
    <div class="p-3.5 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-black/40 shrink-0 flex items-center justify-between gap-2">
        <!-- Left: Export .md (TASK-1005) -->
        <button type="button" onclick="exportCurrentMarkdown()" id="btn-canvas-export"
                class="px-3 py-1.5 border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 rounded-lg text-xs font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 flex items-center gap-1.5 transition-colors shadow-2xs"
                title="Unduh file dokumen (.md)">
            <svg class="w-3.5 h-3.5 text-zinc-500 dark:text-zinc-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            <span>Unduh Dokumen (.md)</span>
        </button>

        <!-- Right: Approve / Reject / Handoff to Generator -->
        <div class="flex items-center gap-1.5" id="canvas-action-buttons">
            <!-- Dynamic buttons injected based on active version status -->
        </div>
    </div>
</aside>
