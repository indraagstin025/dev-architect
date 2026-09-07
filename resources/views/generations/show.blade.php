@extends('layouts.app')

@php
    $headerTitle = 'Tinjau Rancangan Database';
    $headerSubtitle = 'Pratinjau Aman — belum ada file yang disimpan ke folder proyek Anda';
@endphp

@section('topbar-actions')
<span id="status-badge" class="px-2.5 py-1 rounded-md text-xs font-medium bg-emerald-500/10 text-emerald-600 dark:text-[#3ECF8E] border border-emerald-500/30">Draf Pratinjau</span>
@endsection

@section('content')
<div class="max-w-7xl mx-auto space-y-5" data-generation-id="{{ $generationId }}">

    <!-- State: antrean / processing (Radix Minimalist) -->
    <div id="state-pending" class="bg-white dark:bg-[#0c0c0e] border border-zinc-200 dark:border-zinc-800 rounded-xl p-12 text-center space-y-3.5 transition-colors">
        <div class="w-8 h-8 mx-auto rounded-full border-2 border-zinc-300 dark:border-zinc-800 border-t-zinc-900 dark:border-t-white animate-spin"></div>
        <h3 class="text-sm font-semibold text-zinc-900 dark:text-white" id="pending-title">AI sedang merancang tabel dan struktur database...</h3>
        <p class="text-xs text-zinc-500" id="pending-status">Status proses: Sedang Menunggu...</p>
        <button type="button" id="btn-cancel-job" onclick="cancelJob()"
                class="px-3.5 py-1.5 rounded-lg text-xs font-medium text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white bg-zinc-100 dark:bg-zinc-900 hover:bg-zinc-200 dark:hover:bg-zinc-800 border border-zinc-200 dark:border-zinc-800 transition-colors shadow-xs">
            Batalkan Proses
        </button>
    </div>

    <!-- State: gagal -->
    <div id="state-failed" class="hidden bg-white dark:bg-[#0c0c0e] border border-red-300 dark:border-red-500/30 rounded-xl p-6 space-y-3 transition-colors">
        <h3 class="text-sm font-semibold text-red-600 dark:text-red-400">Gagal membuat rancangan database</h3>
        <p class="text-xs text-zinc-800 dark:text-zinc-300 font-mono bg-zinc-50 dark:bg-zinc-950 p-3 rounded-lg border border-zinc-200 dark:border-zinc-800 break-words" id="failed-message"></p>
        <a href="{{ url('/generator') }}" class="inline-flex px-3.5 py-1.5 bg-zinc-100 dark:bg-zinc-900 hover:bg-zinc-200 dark:hover:bg-zinc-800 text-zinc-800 dark:text-zinc-200 rounded-lg text-xs font-medium border border-zinc-200 dark:border-zinc-800 shadow-xs transition-colors">Kembali ke Pembuat Database</a>
    </div>

    <!-- State: siap -->
    <div id="state-ready" class="hidden space-y-5">
        <div id="lint-warnings" class="hidden bg-amber-50 dark:bg-zinc-900/60 border border-amber-200 dark:border-zinc-800 rounded-xl p-4 text-xs text-amber-900 dark:text-zinc-300 transition-colors">
            <div class="font-semibold mb-1 text-amber-950 dark:text-white">Catatan saran perbaikan (opsional):</div>
            <ul class="list-disc ml-5 space-y-1 font-mono text-[11px] text-amber-800 dark:text-zinc-400"></ul>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <!-- ERD Canvas Box -->
            <div class="bg-white dark:bg-[#0c0c0e] border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-xs overflow-hidden flex flex-col transition-colors">
                <div class="px-4 py-2.5 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between flex-wrap gap-2">
                    <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider">Diagram Relasi Tabel (ERD)</span>
                    <div class="flex items-center gap-1">
                        <button type="button" onclick="window.ErdViewer.zoomOut()" class="px-2 py-1 bg-zinc-100 dark:bg-zinc-900 hover:bg-zinc-200 dark:hover:bg-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded-md text-xs text-zinc-700 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-white transition-colors" title="Perkecil">−</button>
                        <span id="zoom-label" class="text-[11px] font-mono text-zinc-500 dark:text-zinc-400 w-11 text-center">100%</span>
                        <button type="button" onclick="window.ErdViewer.zoomIn()" class="px-2 py-1 bg-zinc-100 dark:bg-zinc-900 hover:bg-zinc-200 dark:hover:bg-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded-md text-xs text-zinc-700 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-white transition-colors" title="Perbesar">+</button>
                        <button type="button" onclick="window.ErdViewer.zoomReset()" class="px-2 py-1 bg-zinc-100 dark:bg-zinc-900 hover:bg-zinc-200 dark:hover:bg-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded-md text-xs text-zinc-700 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-white transition-colors">Semula</button>
                        <span class="w-px h-3.5 bg-zinc-200 dark:bg-zinc-800 mx-1"></span>
                        <button type="button" onclick="window.ErdViewer.exportSVG()" class="px-2 py-1 bg-zinc-100 dark:bg-zinc-900 hover:bg-zinc-200 dark:hover:bg-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded-md text-xs text-zinc-700 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-white transition-colors">Unduh SVG</button>
                        <button type="button" onclick="window.ErdViewer.exportPNG()" class="px-2 py-1 bg-zinc-100 dark:bg-zinc-900 hover:bg-zinc-200 dark:hover:bg-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded-md text-xs text-zinc-700 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-white transition-colors">Unduh PNG</button>
                    </div>
                </div>
                <div id="erd-viewport" class="overflow-auto max-h-[560px] min-h-[340px] bg-zinc-50 dark:bg-black transition-colors" style="cursor: grab;">
                    <div id="erd-inner" class="origin-top-left inline-block min-w-full p-6">
                        <div id="erd-canvas" class="flex justify-center"></div>
                    </div>
                </div>
                <div id="erd-error" class="hidden p-4 text-xs">
                    <p class="text-red-500 dark:text-red-400 font-semibold mb-2">Diagram gagal ditampilkan, teks struktur:</p>
                    <pre data-erd-raw class="text-zinc-600 dark:text-zinc-400 font-mono whitespace-pre-wrap bg-zinc-50 dark:bg-zinc-950 p-3 rounded-lg border border-zinc-200 dark:border-zinc-800"></pre>
                </div>
                <p class="px-4 py-2 text-[10px] text-zinc-500 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-950/40">Tahan & geser untuk melihat sekeliling • Putar scroll mouse untuk memperbesar/memperkecil</p>
            </div>

            <!-- Code Review Box -->
            <div class="bg-white dark:bg-[#0c0c0e] border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-xs overflow-hidden flex flex-col transition-colors">
                <div class="px-4 py-2.5 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
                    <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider">File Kode Database <span id="active-filename" class="text-zinc-500 normal-case ml-1 font-mono"></span></span>
                    <div class="flex items-center gap-2">
                        <button type="button" id="btn-edit-toggle" onclick="window.ErdViewer.toggleEdit()" class="px-3 py-1 bg-zinc-100 dark:bg-zinc-900 hover:bg-zinc-200 dark:hover:bg-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded-md text-xs text-zinc-700 dark:text-zinc-200 transition-colors shadow-xs">Ubah Kode</button>
                        <button type="button" id="btn-save-draft" onclick="window.ErdViewer.saveDraft()" class="hidden px-3 py-1 bg-black text-white hover:bg-zinc-800 dark:bg-white dark:text-black dark:hover:bg-zinc-200 font-semibold rounded-md text-xs shadow-xs transition-colors">Simpan Perubahan Draf</button>
                    </div>
                </div>
                <div class="grid grid-cols-[180px_1fr] gap-0 flex-1 min-h-[340px]">
                    <div id="file-tabs" class="border-r border-zinc-200 dark:border-zinc-800 p-2 space-y-1 overflow-y-auto max-h-[560px] bg-zinc-50/50 dark:bg-zinc-950/30"></div>
                    <div class="p-3 overflow-hidden bg-zinc-100 dark:bg-black transition-colors">
                        <div id="code-view" class="overflow-auto max-h-[520px]"></div>
                        <div id="code-edit-wrap" class="hidden h-full">
                            <textarea id="code-editor" spellcheck="false"
                                      class="w-full h-[520px] bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-lg p-3 text-xs font-mono text-zinc-900 dark:text-zinc-100 leading-relaxed focus:border-zinc-900 dark:focus:border-zinc-400 focus:ring-1 focus:ring-zinc-900 dark:focus:ring-zinc-400"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inject Card (Radix Solid CTA) -->
        <div class="bg-white dark:bg-[#0c0c0e] border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 transition-colors">
            <div>
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Simpan File ke Folder Proyek Anda</h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Periksa lokasi folder dan daftar file sebelum disimpan. Status akan diperbarui menjadi <span class="font-medium text-zinc-800 dark:text-zinc-200">Tersimpan ke Proyek</span>.</p>
            </div>
            <button type="button" id="btn-inject" onclick="openInjectModal()"
                    class="px-5 py-2 bg-black text-white hover:bg-zinc-800 dark:bg-white dark:text-black dark:hover:bg-zinc-200 disabled:opacity-50 font-semibold rounded-lg text-xs transition-colors shadow-xs shrink-0 flex items-center gap-2 focus:ring-2 focus:ring-emerald-500/40">
                Terapkan File ke Proyek
            </button>
        </div>
        <div id="inject-result" class="hidden bg-emerald-50 text-emerald-950 dark:bg-emerald-950/40 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800 rounded-xl p-4 text-xs transition-colors">
            <div class="font-semibold mb-1.5 text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span>File berhasil disimpan ke folder proyek:</span>
            </div>
            <ul class="space-y-1 text-emerald-800 dark:text-emerald-300"></ul>
        </div>
    </div>

</div>

<!-- Modal: Konfirmasi Injeksi (Radix Monochrome) -->
<div id="inject-modal" class="hidden fixed inset-0 z-50 bg-black/60 dark:bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-[#0c0c0e] border border-zinc-200 dark:border-zinc-800 rounded-xl max-w-lg w-full p-5 shadow-2xl space-y-4 transition-colors">
        <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-800 pb-3">
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Konfirmasi Simpan File ke Proyek</h3>
            <button type="button" onclick="closeInjectModal()" class="text-zinc-400 hover:text-zinc-900 dark:hover:text-white text-lg leading-none">&times;</button>
        </div>
        <div id="inject-preview-loading" class="text-xs text-zinc-500 dark:text-zinc-400">Memeriksa folder tujuan...</div>
        <div id="inject-preview-body" class="hidden space-y-3.5 text-xs">
            <div>
                <div class="text-[11px] text-zinc-500 uppercase tracking-wider font-semibold mb-1">Folder Tujuan Proyek</div>
                <div class="font-mono text-zinc-800 dark:text-zinc-200 bg-zinc-100 dark:bg-zinc-950 p-2 rounded-lg border border-zinc-200 dark:border-zinc-800 break-all text-[11px]" id="preview-target"></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <div class="text-[11px] text-zinc-500 uppercase tracking-wider font-semibold mb-1">File Baru yang Akan Dibuat (<span id="preview-new-count">0</span>)</div>
                    <ul id="preview-new" class="font-mono text-zinc-700 dark:text-zinc-300 space-y-0.5 max-h-32 overflow-y-auto text-[11px]"></ul>
                    <p class="text-[10px] text-zinc-500 mt-1">Nama file migrasi otomatis diberi tanggal & waktu.</p>
                </div>
                <div>
                    <div class="text-[11px] text-zinc-500 uppercase tracking-wider font-semibold mb-1">File yang Sudah Ada Sebelumnya (<span id="preview-existing-count">0</span>)</div>
                    <ul id="preview-existing" class="font-mono text-zinc-500 space-y-0.5 max-h-32 overflow-y-auto text-[11px]"></ul>
                </div>
            </div>
            <div id="preview-already" class="hidden bg-amber-50 dark:bg-zinc-900 border border-amber-200 dark:border-zinc-800 rounded-lg p-2.5 text-amber-900 dark:text-zinc-300">
                Rancangan ini sudah pernah disimpan ke proyek ini sebelumnya.
                <label class="mt-1.5 flex items-center gap-2 cursor-pointer text-xs">
                    <input type="checkbox" id="force-inject" class="accent-amber-500 dark:accent-white"> Timpa file yang sudah ada
                </label>
            </div>
        </div>
        <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-zinc-200 dark:border-zinc-800">
            <button type="button" onclick="closeInjectModal()" class="px-3.5 py-1.5 rounded-lg text-xs font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">Batal</button>
            <button type="button" id="btn-confirm-inject" onclick="confirmInject()" class="px-4 py-1.5 bg-black text-white hover:bg-zinc-800 dark:bg-white dark:text-black dark:hover:bg-zinc-200 font-semibold rounded-lg text-xs transition-colors shadow-xs">Ya, Simpan ke Proyek</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@vite(['resources/js/erd-viewer.js'])
<script>
const generationId = document.querySelector('[data-generation-id]').dataset.generationId;
let pollTimer = null;

async function pollStatus() {
    try {
        const res = await window.api(`/api/generations/${generationId}/status`);
        const st = res.data.job_status;
        document.getElementById('pending-status').textContent = `Status antrean: ${st}`;

        if (st === 'ready') {
            clearInterval(pollTimer);
            await loadReady(res.data.job_warnings || []);
        } else if (st === 'failed' || st === 'cancelled') {
            clearInterval(pollTimer);
            showFailed(st === 'cancelled' ? 'Permintaan dibatalkan.' : (res.data.job_error || 'Gagal tanpa detail.'));
        }
    } catch (e) {}
}

async function loadReady(warnings) {
    document.getElementById('state-pending').classList.add('hidden');
    document.getElementById('state-ready').classList.remove('hidden');
    const full = await window.api(`/api/generations/${generationId}`);
    window.ErdViewer.init({
        generationId,
        files: full.data.migration_files || [],
        erd: full.data.erd_mermaid_text || '',
        warnings,
    });
}

function showFailed(msg) {
    document.getElementById('state-pending').classList.add('hidden');
    document.getElementById('state-failed').classList.remove('hidden');
    document.getElementById('failed-message').textContent = msg;
}

async function cancelJob() {
    try {
        await window.api(`/api/generations/${generationId}/cancel`, { method: 'POST' });
        clearInterval(pollTimer);
        showFailed('Permintaan dibatalkan.');
    } catch (e) {}
}

function fillList(elId, items, emptyText) {
    const ul = document.getElementById(elId);
    ul.innerHTML = '';
    if (!items.length) {
        const li = document.createElement('li');
        li.className = 'text-zinc-500 italic';
        li.textContent = emptyText;
        ul.appendChild(li);
        return;
    }
    items.forEach((name) => {
        const li = document.createElement('li');
        li.className = 'truncate';
        li.title = name;
        li.textContent = name;
        ul.appendChild(li);
    });
}

async function openInjectModal() {
    document.getElementById('inject-modal').classList.remove('hidden');
    document.getElementById('inject-preview-loading').classList.remove('hidden');
    document.getElementById('inject-preview-body').classList.add('hidden');
    try {
        const res = await window.api(`/api/generations/${generationId}/conflicts`);
        const d = res.data;
        document.getElementById('preview-target').textContent = d.target_directory || '-';
        document.getElementById('preview-new-count').textContent = (d.proposed_files || []).length;
        document.getElementById('preview-existing-count').textContent = (d.existing_files || []).length;
        fillList('preview-new', d.proposed_files || [], '—');
        fillList('preview-existing', d.existing_files || [], 'Folder masih kosong.');
        document.getElementById('preview-already').classList.toggle('hidden', !d.already_injected);
        document.getElementById('inject-preview-loading').classList.add('hidden');
        document.getElementById('inject-preview-body').classList.remove('hidden');
    } catch (e) {
        closeInjectModal();
    }
}

function closeInjectModal() {
    document.getElementById('inject-modal').classList.add('hidden');
}

function confirmInject() {
    const force = document.getElementById('force-inject')?.checked || false;
    window.ErdViewer.inject(force);
}

async function initPolling() {
    while (typeof window.api !== 'function') {
        await new Promise((r) => setTimeout(r, 20));
    }
    pollStatus();
    pollTimer = setInterval(pollStatus, 2000);
}

initPolling();
</script>
@endpush
