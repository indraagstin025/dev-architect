<!-- Modal Proyek Baru -->
<div id="newdoc-modal" class="hidden fixed inset-0 z-50 bg-black/60 dark:bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-[#0c0c0e] border border-zinc-200 dark:border-zinc-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-zinc-900 dark:text-white">Proyek Dokumen Baru</h3>
            <button type="button" onclick="closeNewDocModal()" class="p-1 rounded-lg text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">Judul Proyek</label>
                <input type="text" id="newdoc-title" maxlength="255" placeholder="mis. Penjualan Barang Bekas"
                       class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs focus:ring-1 focus:ring-emerald-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">Ceritakan Ide Aplikasi Anda (Opsional)</label>
                <textarea id="newdoc-desc" rows="4" maxlength="5000" placeholder="Ceritakan konsep, pengguna target, dan fitur utama yang diinginkan..."
                          class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs focus:ring-1 focus:ring-emerald-500 focus:outline-none"></textarea>
            </div>
        </div>
        <div class="flex justify-end gap-2 pt-1">
            <button type="button" onclick="closeNewDocModal()" class="px-4 py-2 text-xs text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 font-medium">Batal</button>
            <button type="button" onclick="createDocProject()" class="px-5 py-2 bg-black text-white dark:bg-white dark:text-black rounded-xl text-xs font-semibold shadow-xs hover:opacity-90 transition-opacity">Buat Proyek</button>
        </div>
    </div>
</div>

<!-- Modal Simpan Snapshot Draf -->
<div id="snapshot-modal" class="hidden fixed inset-0 z-50 bg-black/60 dark:bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-[#0c0c0e] border border-zinc-200 dark:border-zinc-800 rounded-2xl max-w-2xl w-full p-6 space-y-4 shadow-2xl">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-zinc-900 dark:text-white">Simpan Draf ke Lembar Dokumen</h3>
            <button type="button" onclick="closeSnapshotModal()" class="p-1 rounded-lg text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="flex items-center gap-3">
            <div class="flex-1">
                <label class="block text-[11px] font-semibold text-zinc-500 mb-1">Tipe Dokumen</label>
                <select id="snapshot-type" class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs focus:ring-1 focus:ring-emerald-500">
                    <option value="urd">URD (User Requirements Document)</option>
                    <option value="prd">PRD (Product Requirements Document)</option>
                    <option value="srs">SRS (Software Requirements Specification)</option>
                    <option value="sysdesign">System Design & ERD Specification</option>
                </select>
            </div>
            <div class="pt-5">
                <button type="button" onclick="fillFromLastAssistant()" class="px-3 py-2 border border-zinc-200 dark:border-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-xl text-xs text-zinc-700 dark:text-zinc-300 font-medium transition-colors">
                    Ambil dari Balasan Terakhir
                </button>
            </div>
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-zinc-500 mb-1">Isi Dokumen Markdown</label>
            <textarea id="snapshot-content" rows="11" maxlength="200000" placeholder="Tempel atau ketik isi draf dokumen..."
                      class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-xl p-3 text-xs font-mono focus:ring-1 focus:ring-emerald-500 focus:outline-none"></textarea>
        </div>
        <div class="flex justify-end gap-2 pt-1">
            <button type="button" onclick="closeSnapshotModal()" class="px-4 py-2 text-xs text-zinc-500">Batal</button>
            <button type="button" onclick="saveSnapshot()" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-semibold shadow-xs">Simpan ke Lembar Dokumen</button>
        </div>
    </div>
</div>

<!-- Modal Privasi Model Gratis OpenRouter -->
<div id="privacy-modal" class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-[#0c0c0e] border border-amber-300 dark:border-amber-800/80 rounded-2xl max-w-md w-full p-6 space-y-3.5 shadow-2xl">
        <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 rounded-lg bg-amber-500/10 text-amber-500 flex items-center justify-center shrink-0 border border-amber-500/20">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <h3 class="text-sm font-bold text-zinc-900 dark:text-white">Catatan Privasi Model Gratis</h3>
        </div>
        <p class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
            Model berlabel <span class="font-mono bg-zinc-100 dark:bg-zinc-800 px-1 rounded">:free</span> (model gratis seperti Gemma atau Llama) dapat mencatat isi percakapan untuk pelatihan AI menurut kebijakan penyedia model. Sebaiknya hindari mengirimkan informasi rahasia.
        </p>
        <div class="flex justify-end pt-2">
            <button type="button" onclick="ackPrivacy()" class="px-4 py-2 bg-black text-white dark:bg-white dark:text-black rounded-xl text-xs font-semibold shadow-xs">Saya Mengerti</button>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Sukses Jadikan Proyek di Dashboard (TASK-M2-01) -->
<div id="promote-dashboard-modal" class="hidden fixed inset-0 z-50 bg-black/60 dark:bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-[#0c0c0e] border border-zinc-200 dark:border-zinc-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl animate-in fade-in zoom-in duration-150">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-white">Diterbitkan ke Dashboard! 🎉</h3>
                    <button type="button" onclick="closePromoteDashboardModal()" class="p-1 rounded-lg text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                    Konsep arsitektur Anda telah resmi terdaftar sebagai proyek draf di Dashboard.
                </p>
            </div>
        </div>

        <div class="p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800/80 space-y-2">
            <div class="flex items-center justify-between text-xs">
                <span class="text-zinc-500 font-medium">Nama Proyek:</span>
                <span id="promote-modal-project-name" class="font-semibold text-zinc-900 dark:text-white font-mono">-</span>
            </div>
            <div class="flex items-center justify-between text-xs">
                <span class="text-zinc-500 font-medium">Target Framework:</span>
                <span id="promote-modal-framework" class="font-semibold text-emerald-600 dark:text-emerald-400 font-mono uppercase text-[11px] px-2 py-0.5 rounded-md bg-emerald-500/10 border border-emerald-500/20">-</span>
            </div>
            <div class="flex items-center justify-between text-xs">
                <span class="text-zinc-500 font-medium">Status Siklus:</span>
                <span class="inline-flex items-center gap-1 text-[11px] font-medium text-amber-600 dark:text-amber-400 bg-amber-500/10 border border-amber-500/20 px-2 py-0.5 rounded-md">
                    <span>Tahap 1/4</span> • <span>Ide & Dokumen</span>
                </span>
            </div>
        </div>

        <p class="text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed">
            Anda dapat tetap berada di sini untuk mematangkan dokumen arsitektur dan skema database, atau membuka Dashboard untuk melihat kartu proyek.
        </p>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800/80">
            <button type="button" onclick="closePromoteDashboardModal()"
                    class="px-4 py-2 text-xs font-medium text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-xl transition-colors">
                Tetap di Ruang Chat
            </button>
            <a href="/"
               class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-semibold shadow-sm transition-all">
                <span>🚀 Buka Dashboard</span>
            </a>
        </div>
    </div>
</div>

