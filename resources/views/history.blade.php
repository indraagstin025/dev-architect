@extends('layouts.app')

@php
    $headerTitle = 'Riwayat Pembuatan';
    $headerSubtitle = 'Daftar draf dan file database yang telah dibuat per proyek';

    $projects = app(\App\Services\ProjectService::class)->listProjects();
    $activeProjectId = \App\Models\AppSetting::get('active_project_id');
    $activeProject = $activeProjectId
        ? $projects->firstWhere('id', $activeProjectId)
        : $projects->first();
@endphp

@section('content')
<div class="max-w-5xl mx-auto space-y-4" data-active-project="{{ $activeProject ? $activeProject->id : '' }}">

    @if(!$activeProject)
        <div class="bg-white dark:bg-zinc-900/40 border border-zinc-200 dark:border-zinc-800 rounded-xl p-10 text-center space-y-3.5 transition-colors">
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Belum Ada Proyek Terdaftar</h3>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">Daftarkan atau buka proyek terlebih dahulu untuk melihat riwayat pembuatan database.</p>
            <a href="{{ url('/dashboard') }}" class="inline-flex px-4 py-2 bg-black text-white hover:bg-zinc-800 dark:bg-white dark:text-black dark:hover:bg-zinc-200 font-semibold rounded-lg text-xs transition-colors shadow-xs">
                Ke Halaman Proyek
            </a>
        </div>
    @else
        <!-- Top Controls: Project Selector & New Generation Button -->
        <div class="flex items-center justify-between flex-wrap gap-3 pb-1 border-b border-zinc-200 dark:border-zinc-800/60">
            <div class="flex items-center gap-3 flex-wrap">
                <div class="flex items-center gap-2">
                    <span class="text-[11px] text-zinc-500 uppercase tracking-wider font-semibold">Proyek:</span>
                    <select id="project-selector" 
                            onchange="switchProjectHistory(this.value)" 
                            class="bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 focus:border-zinc-900 dark:focus:border-zinc-400 focus:ring-1 focus:ring-zinc-900 dark:focus:ring-zinc-400 rounded-lg text-xs text-zinc-900 dark:text-white px-3 py-1.5 font-medium transition-colors">
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" {{ $activeProject->id === $p->id ? 'selected' : '' }}>
                                {{ $p->project_name }} ({{ $p->framework_type->value ?? $p->framework_type }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <span class="text-[11px] text-zinc-500 font-mono truncate hidden md:inline" id="project-path-display">{{ $activeProject->absolute_path }}</span>
                <div class="flex items-center gap-2">
                    <span class="text-[11px] text-zinc-500 uppercase tracking-wider font-semibold">Urut:</span>
                    <select id="history-sort"
                            onchange="changeHistorySort(this.value)"
                            class="bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 focus:border-zinc-900 dark:focus:border-zinc-400 rounded-lg text-xs text-zinc-900 dark:text-white px-2.5 py-1.5 transition-colors">
                        <option value="created_at|desc">Terbaru</option>
                        <option value="created_at|asc">Terlama</option>
                        <option value="status|asc">Status Penyimpanan</option>
                        <option value="job_status|asc">Status Pemrosesan</option>
                        <option value="target_framework|asc">Framework</option>
                    </select>
                </div>
            </div>

            <a href="{{ url('/generator') }}" class="px-3.5 py-1.5 bg-black text-white hover:bg-zinc-800 dark:bg-white dark:text-black dark:hover:bg-zinc-200 font-semibold rounded-lg text-xs transition-colors shadow-xs flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                Buat Database Baru
            </a>
        </div>

        <!-- Loading Spinner -->
        <div id="history-loading" class="py-12 text-center space-y-2">
            <div class="w-7 h-7 mx-auto rounded-full border-2 border-zinc-300 dark:border-zinc-800 border-t-zinc-900 dark:border-t-white animate-spin"></div>
            <p class="text-xs text-zinc-500">Memuat riwayat pembuatan...</p>
        </div>

        <!-- History Items Container -->
        <div id="history-list" class="space-y-2.5 hidden"></div>

        <!-- Empty State -->
        <div id="history-empty" class="hidden bg-white dark:bg-zinc-900/40 border border-zinc-200 dark:border-zinc-800 rounded-xl p-10 text-center space-y-2 transition-colors">
            <p class="text-xs text-zinc-500 dark:text-zinc-400">Belum ada rancangan database untuk proyek ini.</p>
            <a href="{{ url('/generator') }}" class="inline-block text-xs text-zinc-900 dark:text-white hover:underline font-medium">Buat struktur database sekarang &rarr;</a>
        </div>

        <!-- Pagination Controls -->
        <div id="history-pagination" class="hidden flex items-center justify-between pt-2">
            <button type="button" 
                    id="btn-prev" 
                    disabled 
                    onclick="loadHistory(historyPage - 1)" 
                    class="px-3.5 py-1.5 bg-zinc-100 dark:bg-zinc-900 hover:bg-zinc-200 dark:hover:bg-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded-lg text-xs text-zinc-700 dark:text-zinc-300 disabled:opacity-30 disabled:pointer-events-none transition-colors shadow-xs">
                ← Sebelumnya
            </button>
            <span id="history-meta" class="text-xs text-zinc-500 font-medium"></span>
            <button type="button" 
                    id="btn-next" 
                    disabled 
                    onclick="loadHistory(historyPage + 1)" 
                    class="px-3.5 py-1.5 bg-zinc-100 dark:bg-zinc-900 hover:bg-zinc-200 dark:hover:bg-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded-lg text-xs text-zinc-700 dark:text-zinc-300 disabled:opacity-30 disabled:pointer-events-none transition-colors shadow-xs">
                Berikutnya →
            </button>
        </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
let historyPage = 1;
let historySort = 'created_at';
let historyDirection = 'desc';
let currentProjectId = document.getElementById('project-selector')?.value || '{{ $activeProject ? $activeProject->id : '' }}';

const JOB_BADGE = {
    queued: 'bg-zinc-100 text-zinc-600 border-zinc-200 dark:bg-zinc-900 dark:text-zinc-400 dark:border-zinc-800',
    processing: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30 animate-pulse',
    ready: 'bg-emerald-500/10 text-emerald-600 dark:text-[#3ECF8E] border-emerald-500/30 font-medium',
    failed: 'bg-red-50 text-red-600 border-red-200 dark:bg-zinc-900 dark:text-red-400 dark:border-red-500/30',
    cancelled: 'bg-zinc-100 text-zinc-500 border-zinc-200 dark:bg-zinc-900 dark:text-zinc-500 dark:border-zinc-800',
};

function esc(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
}

function switchProjectHistory(newProjectId) {
    currentProjectId = newProjectId;
    loadHistory(1);
}

function changeHistorySort(value) {
    const [sort, direction] = (value || 'created_at|desc').split('|');
    historySort = sort;
    historyDirection = direction === 'asc' ? 'asc' : 'desc';
    loadHistory(1);
}

async function loadHistory(page = 1) {
    if (!currentProjectId) return;
    historyPage = Math.max(1, page);

    const loader = document.getElementById('history-loading');
    const list = document.getElementById('history-list');
    const emptyBox = document.getElementById('history-empty');
    const pagination = document.getElementById('history-pagination');

    if (loader) loader.classList.remove('hidden');
    if (list) list.classList.add('hidden');
    if (emptyBox) emptyBox.classList.add('hidden');
    if (pagination) pagination.classList.add('hidden');

    try {
        const res = await window.api(`/api/projects/${currentProjectId}/generations?page=${historyPage}&sort=${historySort}&direction=${historyDirection}`);
        const pager = res.data;

        list.innerHTML = '';

        if (loader) loader.classList.add('hidden');

        if (!pager || !pager.data || pager.data.length === 0) {
            emptyBox.classList.remove('hidden');
            return;
        }

        list.classList.remove('hidden');
        pagination.classList.remove('hidden');

        document.getElementById('history-meta').textContent = `Hal. ${pager.current_page} dari ${pager.last_page} • Total ${pager.total} riwayat`;
        document.getElementById('btn-prev').disabled = pager.current_page <= 1;
        document.getElementById('btn-next').disabled = pager.current_page >= pager.last_page;

        function getFrameworkIcon(fw) {
            fw = (fw || '').toLowerCase();
            if (fw.includes('prisma')) {
                return `<span class="inline-flex items-center -space-x-1 shrink-0" title="Express + Prisma">
                    <img src="/assets/icons/ExpressJS.svg" class="w-3.5 h-3.5 object-contain rounded-full bg-zinc-100 dark:bg-zinc-950 ring-1 ring-zinc-300 dark:ring-zinc-800 dark:invert-0 invert" alt="Express" loading="lazy" />
                    <img src="/assets/icons/prisma.svg" class="w-3.5 h-3.5 object-contain rounded-full bg-zinc-100 dark:bg-zinc-950 ring-1 ring-zinc-300 dark:ring-zinc-800" alt="Prisma" loading="lazy" />
                </span>`;
            }
            if (fw.includes('drizzle')) {
                return `<span class="inline-flex items-center -space-x-1 shrink-0" title="Express + Drizzle">
                    <img src="/assets/icons/ExpressJS.svg" class="w-3.5 h-3.5 object-contain rounded-full bg-zinc-100 dark:bg-zinc-950 ring-1 ring-zinc-300 dark:ring-zinc-800 dark:invert-0 invert" alt="Express" loading="lazy" />
                    <img src="/assets/icons/drizzle.svg" class="w-3.5 h-3.5 object-contain rounded-full bg-zinc-100 dark:bg-zinc-950 ring-1 ring-zinc-300 dark:ring-zinc-800" alt="Drizzle" loading="lazy" />
                </span>`;
            }
            let file = 'Laravel.svg';
            if (fw.includes('spring') || fw.includes('hibernate')) file = 'Springboot.svg';
            else if (fw.includes('express') || fw.includes('node')) return `<img src="/assets/icons/ExpressJS.svg" class="w-3.5 h-3.5 shrink-0 object-contain inline-block dark:invert-0 invert" alt="${esc(fw)}" loading="lazy" />`;
            return `<img src="/assets/icons/${file}" class="w-3.5 h-3.5 shrink-0 object-contain inline-block" alt="${esc(fw)}" loading="lazy" />`;
        }

        function getDatabaseIcon(dl) {
            dl = (dl || '').toLowerCase();
            let file = 'MySQL.svg';
            if (dl.includes('pg') || dl.includes('postgres')) file = 'PostgreSQL.svg';
            else if (dl.includes('sqlite')) file = 'SQLite.svg';
            else if (dl.includes('sqlserver') || dl.includes('sqlsrv') || dl.includes('mssql')) file = 'SQLServer.svg';
            return `<img src="/assets/icons/${file}" class="w-3.5 h-3.5 shrink-0 object-contain inline-block" alt="${esc(dl)}" loading="lazy" />`;
        }

        const jobLabelMap = {
            queued: 'Menunggu',
            processing: 'Sedang Dibuat',
            ready: 'Selesai',
            failed: 'Gagal',
            cancelled: 'Dibatalkan',
        };

        pager.data.forEach((g) => {
            const card = document.createElement('div');
            card.className = 'bg-white dark:bg-[#0c0c0e] border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 flex flex-col md:flex-row md:items-center gap-3 shadow-xs transition-colors';
            
            const isSaved = g.status === 'injected';
            const statusLabel = isSaved ? 'Tersimpan ke Proyek' : 'Draf Pratinjau';
            const statusClass = isSaved 
                ? 'bg-emerald-500/10 text-emerald-600 dark:text-[#3ECF8E] border-emerald-500/30 font-medium' 
                : 'bg-zinc-100 text-zinc-600 border-zinc-200 dark:bg-zinc-900 dark:text-zinc-400 dark:border-zinc-800';
            
            const jobText = jobLabelMap[g.job_status] || g.job_status;

            card.innerHTML = `
                <div class="flex-1 min-w-0">
                    <div class="text-xs text-zinc-800 dark:text-zinc-200 truncate font-medium">${esc(g.prompt_text)}</div>
                    <div class="flex items-center gap-2 mt-2 flex-wrap">
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-medium bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 text-zinc-700 dark:text-zinc-300">
                            ${getFrameworkIcon(g.target_framework)}
                            <span>${esc(g.target_framework)}</span>
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-medium bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 text-zinc-700 dark:text-zinc-300">
                            ${getDatabaseIcon(g.database_dialect)}
                            <span>${esc(g.database_dialect)}</span>
                        </span>
                        <span class="text-[11px] text-zinc-500">&bull; ${new Date(g.created_at).toLocaleString('id-ID')}</span>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0 flex-wrap">
                    <span class="px-2 py-0.5 rounded text-[11px] font-medium border ${statusClass}">${statusLabel}</span>
                    <span class="px-2 py-0.5 rounded text-[11px] font-medium border ${JOB_BADGE[g.job_status] || JOB_BADGE.queued}">${jobText}</span>
                    <a href="/generations/${g.id}" class="px-3 py-1 bg-zinc-100 dark:bg-zinc-900 hover:bg-zinc-200 dark:hover:bg-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded-lg text-xs text-zinc-700 dark:text-zinc-200 transition-colors shadow-xs">Lihat Rancangan</a>
                </div>`;
            list.appendChild(card);
        });
    } catch (e) {
        if (loader) loader.classList.add('hidden');
        if (emptyBox) emptyBox.classList.remove('hidden');
        console.error('Gagal mengambil data riwayat:', e);
    }
}

// Inisialisasi otomatis: Tunggu hingga window.api siap (Vite module loading)
async function initHistory() {
    while (typeof window.api !== 'function') {
        await new Promise((r) => setTimeout(r, 20));
    }
    if (currentProjectId) {
        await loadHistory(1);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHistory);
} else {
    initHistory();
}
</script>
@endpush
