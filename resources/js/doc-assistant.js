/**
 * DEVArchitect - Document Assistant Core (Gemini & ChatGPT Canvas Edition)
 * Modular client-side controller for /assistant
 */

// Data template brief saran pemicu (Gemini Style)
export const BRIEF_TEMPLATES = [
    'Saya ingin membuat project penjualan barang bekas (marketplace C2C). Ada penjual, pembeli, produk bekas dengan foto & kondisi fisik, keranjang belanja, checkout, transaksi dengan escrow/rekening bersama, serta sistem ulasan.',
    'Saya ingin membuat aplikasi manajemen inventaris & pergudangan. Ada multi-gudang, pencatatan stok masuk dan keluar, opname berkala, cetak barcode/QR, serta laporan mutasi barang.',
    'Saya ingin membuat aplikasi absensi karyawan (HRIS). Ada presensi berbasis koordinat GPS & scan QR dinamis, shift kerja, pengajuan cuti/lembur, serta rekapitulasi kehadiran untuk payroll bulanan.',
    'Saya ingin membuat aplikasi reservasi layanan dan booking janji temu klinik. Ada jadwal dokter, slot waktu konsultasi, antrean real-time, deposit pembayaran, dan pengingat via WhatsApp.',
];

export const STAGES = ['brief', 'urd', 'prd', 'srs', 'sysdesign', 'done'];
export const STAGE_LABELS = {
    brief: 'Ide Awal',
    urd: 'Kebutuhan',
    prd: 'Rincian Fitur',
    srs: 'Spesifikasi',
    sysdesign: 'Desain Database',
    done: 'Siap Dibuat'
};

// Application State
let docProjectId = null;
let docProject = null;
let docVersions = [];
let activeVersion = null;
let pendingAssistantId = null;
let pollTimer = null;
let liveModels = [];
let sectionsMap = {};
let isCanvasOpen = false;
let currentCanvasTab = 'preview';
let oldestMessageId = null;
let hasMoreMessages = false;
let isLoadingOlder = false;

export function esc(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
}

// Auto-expand multiline textarea (Gemini & ChatGPT style, no inner scrollbar for single line)
export function autoExpandTextarea(el) {
    if (!el) return;
    el.style.height = 'auto';
    const maxH = 140;
    if (el.scrollHeight > maxH) {
        el.style.height = maxH + 'px';
        el.style.overflowY = 'auto';
    } else {
        el.style.height = Math.max(el.scrollHeight, 38) + 'px';
        el.style.overflowY = 'hidden';
    }
}

export function handleChatKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendChat(e);
    }
}

// Enhanced Markdown Parser with Table, Code Blocks, Quotes, and Lists support
export function md(src) {
    let h = esc(src || '');
    // Unescape literal <br> tags from AI output (e.g. inside tables, lists, or text)
    h = h.replace(/&lt;br\s*\/?&gt;/gi, '<br>');
    const fences = [];
    
    // Code blocks
    h = h.replace(/```(\w*)\n([\s\S]*?)```/g, (m, lang, code) => {
        const langBadge = lang ? `<span class="text-[10px] text-zinc-500 font-mono uppercase px-2 py-0.5">${lang}</span>` : '';
        fences.push(`
            <div class="my-3 rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-800 bg-zinc-950">
                <div class="flex items-center justify-between px-3 py-1.5 bg-zinc-900/80 border-b border-zinc-800/80">
                    ${langBadge}
                    <button type="button" onclick="copyCodeBlock(this)" class="text-[10px] font-mono text-zinc-400 hover:text-white transition-colors flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        <span>Salin</span>
                    </button>
                </div>
                <pre class="p-3 text-[11px] font-mono text-zinc-200 overflow-x-auto leading-relaxed"><code>${code.replace(/\n$/, '')}</code></pre>
            </div>
        `);
        return `\u0000${fences.length - 1}\u0000`;
    });

    // Typography & rules
    h = h.replace(/^###### (.*)$/gm, '<h6 class="font-bold text-xs mt-3 mb-1 text-zinc-900 dark:text-zinc-200">$1</h6>')
         .replace(/^##### (.*)$/gm, '<h6 class="font-bold text-xs mt-3 mb-1 text-zinc-900 dark:text-zinc-200">$1</h6>')
         .replace(/^#### (.*)$/gm, '<h5 class="font-bold text-xs mt-3.5 mb-1.5 text-zinc-900 dark:text-zinc-100">$1</h5>')
         .replace(/^### (.*)$/gm, '<h4 class="font-bold text-sm mt-4 mb-2 text-zinc-900 dark:text-white">$1</h4>')
         .replace(/^## (.*)$/gm, '<h3 class="font-bold text-sm mt-4 mb-2 text-zinc-900 dark:text-white border-b border-zinc-200 dark:border-zinc-800 pb-1">$1</h3>')
         .replace(/^# (.*)$/gm, '<h2 class="font-bold text-base mt-4 mb-2 text-zinc-900 dark:text-white">$1</h2>')
         .replace(/^(?:---|___|\*\*\*)$/gm, '<hr class="my-4 border-zinc-200 dark:border-zinc-800">')
         .replace(/\*\*([^*]+)\*\*/g, '<strong class="font-semibold text-zinc-900 dark:text-white">$1</strong>')
         .replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em class="italic text-zinc-700 dark:text-zinc-300">$2</em>')
         .replace(/`([^`\n]+)`/g, '<code class="font-mono text-[11px] bg-zinc-100 dark:bg-zinc-800/80 text-emerald-600 dark:text-emerald-400 px-1.5 py-0.5 rounded border border-zinc-200 dark:border-zinc-700/60">$1</code>')
         .replace(/^> (.*)$/gm, '<blockquote class="border-l-2 border-emerald-500/80 pl-3 my-2 text-zinc-600 dark:text-zinc-400 italic text-xs">$1</blockquote>');

    const renderInline = (text) => {
        return (text || '')
            .replace(/&lt;br\s*\/?&gt;/gi, '<br>')
            .replace(/\*\*([^*]+)\*\*/g, '<strong class="font-semibold text-zinc-900 dark:text-white">$1</strong>')
            .replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em class="italic text-zinc-700 dark:text-zinc-300">$2</em>')
            .replace(/`([^`\n]+)`/g, '<code class="font-mono text-[11px] bg-zinc-100 dark:bg-zinc-800/80 text-emerald-600 dark:text-emerald-400 px-1 py-0.5 rounded border border-zinc-200 dark:border-zinc-700/60">$1</code>');
    };

    // Split blocks
    h = h.split(/\n{2,}/).map((block) => {
        const tr = block.trim();
        if (/^\u0000\d+\u0000$/.test(tr)) return block;
        if (/^<(h\d|pre|ul|ol|table|blockquote|hr)/.test(tr)) return block;
        
        const lines = tr.split('\n');

        // Markdown Table Parser (| Header | Header |)
        if (lines.length >= 2 && lines[0].includes('|') && lines[1].includes('|') && lines[1].includes('-')) {
            const parseRow = (rowStr) => rowStr.split('|').map(s => s.trim()).filter((s, idx, arr) => idx > 0 && idx < arr.length);
            const headers = parseRow(lines[0]);
            let rows = [];
            for (let i = 2; i < lines.length; i++) {
                if (lines[i].includes('|')) {
                    rows.push(parseRow(lines[i]));
                }
            }
            let tableHtml = `
                <div class="overflow-x-auto my-3 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-zinc-100 dark:bg-zinc-800/60 border-b border-zinc-200 dark:border-zinc-800">
                                ${headers.map(hdr => `<th class="px-3 py-2 font-semibold text-zinc-900 dark:text-zinc-100">${renderInline(hdr)}</th>`).join('')}
                            </tr>
                        </thead>
                        <tbody>
                            ${rows.map(r => `
                                <tr class="border-b border-zinc-100 dark:border-zinc-800/50 hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition-colors">
                                    ${r.map(cell => `<td class="px-3 py-2 text-zinc-700 dark:text-zinc-300 leading-relaxed">${renderInline(cell)}</td>`).join('')}
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
            return tableHtml;
        }

        // Unordered list
        if (lines.every((l) => /^\s*[-*] /.test(l))) {
            return '<ul class="list-disc ml-5 space-y-1 my-2 text-zinc-700 dark:text-zinc-300">' + lines.map((l) => `<li>${renderInline(l.replace(/^\s*[-*] /, ''))}</li>`).join('') + '</ul>';
        }
        // Ordered list
        if (lines.every((l) => /^\s*\d+\. /.test(l))) {
            return '<ol class="list-decimal ml-5 space-y-1 my-2 text-zinc-700 dark:text-zinc-300">' + lines.map((l) => `<li>${renderInline(l.replace(/^\s*\d+\. /, ''))}</li>`).join('') + '</ol>';
        }

        return `<p class="my-2 leading-relaxed text-zinc-800 dark:text-zinc-200">${tr.replace(/&lt;br\s*\/?&gt;/gi, '<br>').replace(/\n/g, '<br>')}</p>`;
    }).join('');

    return h.replace(/\u0000(\d+)\u0000/g, (m, i) => fences[Number(i)]);
}

export function copyCodeBlock(btn) {
    const code = btn.closest('div').parentElement.querySelector('code');
    if (!code) return;
    navigator.clipboard.writeText(code.textContent);
    window.toast('Kode disalin!', 'success');
}

export function copyMessageText(btn) {
    const text = decodeURIComponent(btn.dataset.content || '');
    navigator.clipboard.writeText(text);
    window.toast('Teks berhasil disalin ke clipboard.', 'success');
}

export function snapshotFromMessage(messageId) {
    const msg = (docProject?.messages || []).find(m => m.id === messageId);
    if (!msg || !msg.content) return;
    document.getElementById('snapshot-content').value = msg.content;
    const curStage = docProject?.stage || 'urd';
    const typeSelect = document.getElementById('snapshot-type');
    if (typeSelect && ['urd', 'prd', 'srs', 'sysdesign'].includes(curStage)) {
        typeSelect.value = curStage;
    }
    openSnapshotModal();
}

// ---------------- STAGE PIPELINE STEPPER ----------------
export function renderStagePipeline() {
    const box = document.getElementById('stage-pipeline');
    const boxMobile = document.getElementById('stage-pipeline-mobile');
    if (!box) return;
    
    box.innerHTML = '';
    if (boxMobile) boxMobile.innerHTML = '';

    const curStage = docProject?.stage || 'brief';
    const curIdx = STAGES.indexOf(curStage);

    STAGES.forEach((s, idx) => {
        const isCompleted = idx < curIdx;
        const isActive = idx === curIdx;

        // Desktop item
        const item = document.createElement('div');
        item.className = 'flex items-center gap-1 text-[11px] font-sans font-medium whitespace-nowrap shrink-0 transition-all ' + (
            isCompleted ? 'text-emerald-600 dark:text-emerald-400 font-semibold' :
            (isActive ? 'text-zinc-900 dark:text-white font-bold bg-white dark:bg-zinc-800 px-2.5 py-0.5 rounded-full shadow-2xs border border-zinc-200/80 dark:border-zinc-700/60' : 'text-zinc-400 dark:text-zinc-500 font-normal')
        );

        let icon = '';
        if (isCompleted) {
            icon = `<span class="w-3.5 h-3.5 rounded-full bg-emerald-500/15 text-emerald-500 flex items-center justify-center shrink-0"><svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></span>`;
        } else if (isActive) {
            icon = `<span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse shrink-0"></span>`;
        } else {
            icon = `<span class="w-3.5 h-3.5 rounded-full border border-zinc-300 dark:border-zinc-700 flex items-center justify-center text-[9px] text-zinc-400 shrink-0">${idx + 1}</span>`;
        }

        item.innerHTML = `${icon}<span>${STAGE_LABELS[s] || s.toUpperCase()}</span>`;
        box.appendChild(item);

        if (idx < STAGES.length - 1) {
            const sep = document.createElement('span');
            sep.className = 'text-zinc-300 dark:text-zinc-700 shrink-0 select-none px-0.5 flex items-center';
            sep.innerHTML = '<svg class="w-2.5 h-2.5 text-zinc-300 dark:text-zinc-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>';
            box.appendChild(sep);
        }

        // Mobile pill
        if (boxMobile) {
            const mEl = document.createElement('span');
            mEl.className = 'px-2 py-0.5 rounded-lg shrink-0 font-mono text-[10px] whitespace-nowrap ' + (
                isCompleted ? 'bg-emerald-500/15 text-emerald-500 font-medium' :
                (isActive ? 'bg-zinc-900 text-white dark:bg-white dark:text-black font-bold' : 'text-zinc-400')
            );
            mEl.textContent = `${idx + 1}.${s}`;
            boxMobile.appendChild(mEl);
        }
    });
}

// ---------------- LOAD PROJECTS & SELECT ----------------
export async function loadDocProjects(selectId = null) {
    try {
        const res = await window.api('/api/docs/projects');
        const sel = document.getElementById('doc-project-selector');
        sel.innerHTML = '<option value="">— Pilih / buat proyek —</option>';
        res.data.forEach((p) => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = `${p.title} [${p.stage}]${p.status === 'archived' ? ' (arsip)' : ''}`;
            sel.appendChild(opt);
        });
        if (selectId) {
            sel.value = selectId;
            await selectDocProject(selectId);
        } else if (res.data.length && !docProjectId) {
            sel.value = res.data[0].id;
            await selectDocProject(res.data[0].id);
        }
    } catch (e) {}
}

export async function selectDocProject(id) {
    docProjectId = id || null;
    if (!docProjectId) {
        document.getElementById('chat-empty').classList.remove('hidden');
        document.getElementById('chat-messages').classList.add('hidden');
        toggleCanvas(false);
        return;
    }

    try {
        const res = await window.api(`/api/docs/projects/${docProjectId}`);
        docProject = res.data;
        docVersions = docProject.versions || [];

        const pagination = docProject.messages_pagination || {};
        hasMoreMessages = !!pagination.has_more;
        oldestMessageId = pagination.oldest_id || (docProject.messages && docProject.messages.length ? docProject.messages[0].id : null);

        renderStagePipeline();
        renderMessages(docProject.messages || []);
        updateCanvasBadge();
        syncModelPicker();
        syncCodeProjectPicker();
        syncGenType();
        updateMenuArchiveLabel();
        updatePromoteDashboardButton();

        // If versions exist, select latest active version in Canvas
        if (docVersions.length) {
            activeVersion = docVersions[docVersions.length - 1];
            populateCanvas();
        } else {
            activeVersion = null;
            populateCanvas();
        }

        if (docProject.messages && docProject.messages.length > 0) {
            document.getElementById('chat-empty').classList.add('hidden');
            document.getElementById('chat-messages').classList.remove('hidden');
        } else {
            document.getElementById('chat-empty').classList.remove('hidden');
            document.getElementById('chat-messages').classList.add('hidden');
        }
    } catch (e) {}
}

function updatePromoteDashboardButton() {
    const btn = document.getElementById('btn-promote-dashboard');
    const menuTxt = document.getElementById('menu-promote-dashboard-text');
    if (!btn) return;

    if (docProject && docProject.code_project_id) {
        btn.classList.remove('hidden');
        btn.innerHTML = `
            <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 13l4 4L19 7" />
            </svg>
            <span>✓ Terdaftar di Dashboard</span>
        `;
        btn.title = 'Proyek draft ini sudah terdaftar di Dashboard. Klik untuk melihat detail atau membuka Dashboard.';
        btn.className = 'hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-emerald-500/40 bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-500/25 text-xs font-semibold transition-all shadow-2xs cursor-pointer';
        if (menuTxt) menuTxt.textContent = 'Lihat Proyek di Dashboard';
    } else {
        btn.classList.remove('hidden');
        btn.innerHTML = `
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4v16m8-8H4" />
            </svg>
            <span>+ Jadikan Proyek di Dashboard</span>
        `;
        btn.title = 'Jadikan ide ini sebagai Proyek di Dashboard (Bisa diinstal kapan saja)';
        btn.className = 'hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20 text-xs font-semibold transition-all shadow-2xs cursor-pointer';
        if (menuTxt) menuTxt.textContent = 'Jadikan Proyek di Dashboard';
    }
}

function updateMenuArchiveLabel() {
    const lbl = document.getElementById('menu-archive-text');
    if (lbl) {
        lbl.textContent = docProject?.status === 'archived' ? 'Buka dari Arsip' : 'Arsipkan Proyek';
    }
}

function updateLoadMoreButton() {
    const container = document.getElementById('load-more-messages-container');
    if (container) {
        container.classList.toggle('hidden', !hasMoreMessages);
    }
}

// ---------------- RENDER MESSAGES ----------------
function tokenFooter(m) {
    if (m.role !== 'assistant') return '';
    const parts = [];
    if (m.prompt_tokens || m.completion_tokens) parts.push(`±${(m.prompt_tokens || 0) + (m.completion_tokens || 0)} token`);
    if (m.ai_model) parts.push(esc(m.ai_model));
    if (m.job_status === 'failed') parts.push(`<span class="text-rose-500">gagal</span>`);
    return parts.length ? `<span class="text-[10px] font-mono text-zinc-400 dark:text-zinc-500">${parts.join(' • ')}</span>` : '';
}

function renderAssistantContent(m, pending) {
    if (pending) {
        return `<span class="text-zinc-400 italic">Menyusun arsitektur sistem...</span> <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block animate-ping ml-1"></span>`;
    }
    if (m.job_status === 'failed') {
        const err = esc(m.job_error || 'Gagal memproses respons AI.');
        const isLimit = /rate limit|429|kuota|limit|tercapai/i.test(err);
        return `
            <div class="p-3.5 rounded-2xl border border-rose-500/25 bg-rose-500/10 text-rose-800 dark:text-rose-200 text-xs space-y-2 shadow-2xs">
                <div class="flex items-center gap-1.5 font-medium text-rose-600 dark:text-rose-400">
                    <span class="text-sm">⚠️</span>
                    <span>${isLimit ? 'Batas Penggunaan Tercapai (Rate Limit / Quota)' : 'Gagal Menghubungi Model AI'}</span>
                </div>
                <p class="leading-relaxed text-[11px] opacity-90">${err}</p>
                <div class="text-[11px] text-zinc-600 dark:text-zinc-400 pt-1.5 border-t border-rose-500/15">
                    💡 <b>Saran:</b> ${isLimit ? 'Tunggu sekitar 1 menit jika terkena batas kecepatan, atau pilih model lain pada dropdown di atas.' : 'Periksa koneksi internet atau coba pilih model AI lain pada dropdown.'}
                </div>
            </div>
        `;
    }
    return md(m.content);
}

function createMessageElement(m) {
    const wrap = document.createElement('div');
    wrap.className = 'w-full flex ' + (m.role === 'user' ? 'justify-end' : 'justify-start');
    const pending = (m.job_status === 'queued' || m.job_status === 'processing');

    if (m.role === 'user') {
        // User message bubble (ChatGPT / Gemini style: snug right-aligned pill, fluid)
        wrap.innerHTML = `
            <div class="w-fit max-w-[85%] sm:max-w-xl md:max-w-2xl ml-auto rounded-3xl rounded-br-sm px-4 py-2.5 bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs sm:text-sm leading-relaxed border border-zinc-200/50 dark:border-zinc-700/40 shadow-2xs">
                ${esc(m.content)}
            </div>
        `;
    } else {
        // Assistant message (Fluid width, prose style with star badge & action buttons)
        wrap.innerHTML = `
            <div class="w-full flex gap-3 max-w-full">
                <div class="w-7 h-7 rounded-xl bg-gradient-to-tr from-emerald-500/20 via-teal-500/20 to-blue-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-500 text-xs shrink-0 shadow-2xs mt-0.5">
                    <svg class="w-3.5 h-3.5 text-emerald-500 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0 space-y-2">
                    <div class="text-xs sm:text-sm text-zinc-800 dark:text-zinc-200 leading-relaxed select-text">
                        ${renderAssistantContent(m, pending)}
                    </div>
                    ${!pending && m.content ? `
                        <div class="flex items-center gap-2 pt-1.5 flex-wrap">
                            <button type="button" onclick="snapshotFromMessage('${m.id}')" class="px-2.5 py-1 rounded-lg border border-zinc-200 dark:border-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-[11px] text-zinc-600 dark:text-zinc-400 hover:text-emerald-500 flex items-center gap-1.5 transition-colors shadow-2xs">
                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span>Buka di Canvas</span>
                            </button>
                            <button type="button" onclick="copyMessageText(this)" data-content="${encodeURIComponent(m.content)}" class="px-2.5 py-1 rounded-lg border border-zinc-200 dark:border-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-[11px] text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white flex items-center gap-1.5 transition-colors shadow-2xs">
                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                                <span>Salin</span>
                            </button>
                            ${tokenFooter(m)}
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }
    wrap.dataset.messageId = m.id;
    return wrap;
}

function renderMessages(messages, shouldScroll = true) {
    const box = document.getElementById('chat-messages-stream') || document.getElementById('chat-messages');
    box.innerHTML = '';
    
    messages.forEach((m) => {
        box.appendChild(createMessageElement(m));
    });

    updateLoadMoreButton();

    if (shouldScroll) {
        const scrollContainer = document.getElementById('chat-scroll-container');
        if (scrollContainer) {
            scrollContainer.scrollTop = scrollContainer.scrollHeight;
        }
    }
}

// ---------------- LAZY LOAD MESSAGES (TASK-M2-06) ----------------
export async function loadOlderMessages() {
    if (!docProjectId || !oldestMessageId || isLoadingOlder) return;
    isLoadingOlder = true;
    const btn = document.getElementById('btn-load-more-messages');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<span class="w-3 h-3 border-2 border-zinc-400 border-t-zinc-700 dark:border-t-zinc-200 rounded-full animate-spin"></span> <span>Memuat pesan sebelumnya...</span>`;
    }

    const scrollContainer = document.getElementById('chat-scroll-container');
    const prevScrollHeight = scrollContainer ? scrollContainer.scrollHeight : 0;
    const prevScrollTop = scrollContainer ? scrollContainer.scrollTop : 0;

    try {
        const res = await window.api(`/api/docs/projects/${docProjectId}/messages?before_id=${oldestMessageId}&limit=20`);
        const older = res.data || [];
        hasMoreMessages = !!res.has_more;
        if (res.oldest_id) {
            oldestMessageId = res.oldest_id;
        } else if (older.length > 0) {
            oldestMessageId = older[0].id;
        }

        const box = document.getElementById('chat-messages-stream') || document.getElementById('chat-messages');
        // Older messages come ordered chronologically (oldest to newest among the slice)
        // We prepend them before the current first message element
        older.slice().reverse().forEach((m) => {
            const el = createMessageElement(m);
            box.insertBefore(el, box.firstChild);
        });

        updateLoadMoreButton();

        // Maintain user view position
        if (scrollContainer) {
            const newScrollHeight = scrollContainer.scrollHeight;
            scrollContainer.scrollTop = (newScrollHeight - prevScrollHeight) + prevScrollTop;
        }
    } catch (e) {
        window.toast('Gagal memuat pesan sebelumnya.', 'error');
    } finally {
        isLoadingOlder = false;
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = `<svg class="w-3.5 h-3.5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg> <span>Muat 20 Pesan Sebelumnya</span>`;
        }
    }
}

// ---------------- CREATE DASHBOARD PROJECT (TASK-M2-01) ----------------
export async function createDashboardProject() {
    if (!docProjectId) {
        window.toast('Pilih atau buat proyek dokumen terlebih dahulu.', 'warning');
        return;
    }

    // Jika sudah pernah diterbitkan ke Dashboard, langsung tampilkan modal info
    if (docProject && docProject.code_project_id) {
        const pop = document.getElementById('project-menu-pop');
        if (pop) pop.classList.add('hidden');
        openPromoteDashboardModal(docProject.code_project || {
            project_name: docProject.title,
            framework_type: docProject.target_framework || 'laravel',
        });
        return;
    }

    const btn = document.getElementById('btn-promote-dashboard');
    const origHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<span class="w-3.5 h-3.5 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin"></span> <span>Menerbitkan...</span>`;
    }

    try {
        const res = await window.api(`/api/docs/projects/${docProjectId}/create-dashboard-project`, {
            method: 'POST'
        });

        if (res.status === 'success' && res.data) {
            docProject.code_project_id = res.data.id;
            docProject.code_project = res.data;
            updatePromoteDashboardButton();

            const pop = document.getElementById('project-menu-pop');
            if (pop) pop.classList.add('hidden');

            window.toast(res.message || 'Proyek draft berhasil dibuat di Dashboard!', 'success');
            openPromoteDashboardModal(res.data);
        } else {
            window.toast(res.message || 'Gagal membuat proyek di Dashboard', 'error');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
        }
    } catch (e) {
        window.toast('Terjadi kesalahan saat membuat proyek di Dashboard.', 'error');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    }
}

export function openPromoteDashboardModal(project) {
    const modal = document.getElementById('promote-dashboard-modal');
    if (!modal) return;
    const nameEl = document.getElementById('promote-modal-project-name');
    const fwEl = document.getElementById('promote-modal-framework');
    if (nameEl) {
        nameEl.textContent = project?.project_name || docProject?.title || '-';
    }
    if (fwEl) {
        fwEl.textContent = (project?.framework_type || docProject?.target_framework || 'laravel').toUpperCase();
    }
    modal.classList.remove('hidden');
}

export function closePromoteDashboardModal() {
    const modal = document.getElementById('promote-dashboard-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

// ---------------- ARCHIVE CHAT SESSION (TASK-M2-08) ----------------
export async function archiveDocChatSession() {
    if (!docProjectId) return;
    const ok = confirm('Arsipkan seluruh obrolan saat ini? Pesan obrolan akan disimpan ke arsip dan tampilan obrolan dimulai baru, namun seluruh versi dokumen pada Lembar Dokumen (Canvas) tetap aman tersimpan.');
    if (!ok) return;

    try {
        const res = await window.api(`/api/docs/projects/${docProjectId}/archive-chat`, {
            method: 'POST'
        });

        if (res.status === 'success') {
            window.toast(res.message || 'Sesi obrolan berhasil diarsipkan.', 'success');
            const pop = document.getElementById('project-menu-pop');
            if (pop) pop.classList.add('hidden');
            await selectDocProject(docProjectId);
        } else {
            window.toast(res.message || 'Gagal mengarsipkan obrolan.', 'error');
        }
    } catch (e) {
        window.toast('Terjadi kesalahan saat mengarsipkan obrolan.', 'error');
    }
}

// ---------------- EXPORT TRANSCRIPT (TASK-M2-09) ----------------
export async function exportDocTranscript() {
    if (!docProjectId) return;
    try {
        const res = await window.api(`/api/docs/projects/${docProjectId}/export-transcript`);
        if (res.status === 'success' && res.data) {
            const blob = new Blob([res.data.transcript_md], { type: 'text/markdown;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = res.data.filename || `transcript-${docProjectId}.md`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            window.toast('Transkrip obrolan berhasil diunduh.', 'success');
            const pop = document.getElementById('project-menu-pop');
            if (pop) pop.classList.add('hidden');
        } else {
            window.toast('Gagal mengekspor transkrip.', 'error');
        }
    } catch (e) {
        window.toast('Terjadi kesalahan saat mengekspor transkrip.', 'error');
    }
}

// ---------------- SEND CHAT & POLLING ----------------
export async function sendChat(e) {
    if (e) e.preventDefault();
    if (!docProjectId) {
        window.toast('Pilih atau buat proyek dokumen terlebih dahulu.', 'warning');
        openNewDocModal();
        return;
    }
    if (typeof navigator !== 'undefined' && !navigator.onLine) {
        window.toast('Anda sedang offline. Dokumen lokal tetap bisa dibaca dan diedit di Canvas, namun pengiriman pesan AI memerlukan koneksi internet.', 'warning', 4000);
        return;
    }
    const input = document.getElementById('chat-input');
    const text = input.value.trim();
    if (!text) return;

    input.value = '';
    autoExpandTextarea(input);

    try {
        const res = await window.api(`/api/docs/projects/${docProjectId}/messages`, {
            method: 'POST',
            body: { content: text }
        });
        pendingAssistantId = res.data.assistant_message.id;
        setBusy(true);
        await selectDocProject(docProjectId);
        clearInterval(pollTimer);
        pollTimer = setInterval(pollAssistant, 2000);
    } catch (err) {}
}

export function sendQuickPrompt(promptText) {
    const input = document.getElementById('chat-input');
    input.value = promptText;
    autoExpandTextarea(input);
    sendChat();
}

async function pollAssistant() {
    if (!pendingAssistantId) return;
    try {
        const res = await window.api(`/api/docs/messages/${pendingAssistantId}/status`);
        const st = res.data.job_status;
        if (['ready', 'failed', 'cancelled'].includes(st)) {
            clearInterval(pollTimer);
            pollTimer = null;
            pendingAssistantId = null;
            setBusy(false);
            if (st === 'failed') {
                const err = res.data.job_error || '';
                const isLimit = /rate limit|429|kuota|limit|tercapai/i.test(err);
                window.toast(
                    isLimit 
                        ? 'Batas penggunaan AI tercapai. Silakan tunggu 1 menit atau pilih model lain di dropdown.' 
                        : 'Gagal memproses pesan AI. Silakan periksa detail error pada pesan.', 
                    'warning', 
                    6000
                );
            }
            await selectDocProject(docProjectId);
        } else {
            await selectDocProject(docProjectId);
        }
    } catch (e) {}
}

function setBusy(on) {
    const busyBox = document.getElementById('chat-busy');
    const sendBtn = document.getElementById('btn-chat-send');
    if (busyBox) {
        busyBox.classList.toggle('hidden', !on);
        busyBox.classList.toggle('flex', on);
    }
    if (sendBtn) {
        sendBtn.disabled = on;
    }
}

export async function cancelAssistant() {
    if (!pendingAssistantId) return;
    try {
        await window.api(`/api/docs/messages/${pendingAssistantId}/cancel`, { method: 'POST' });
        window.toast('Permintaan dibatalkan.', 'info');
    } catch (e) {}
    clearInterval(pollTimer);
    pollTimer = null;
    pendingAssistantId = null;
    setBusy(false);
    await selectDocProject(docProjectId);
}

// ---------------- CANVAS MANAGEMENT (ChatGPT Canvas style) ----------------
export function toggleCanvas(forceState = null) {
    const panel     = document.getElementById('canvas-panel');
    const btn       = document.getElementById('btn-toggle-canvas');
    const studio    = document.getElementById('studio-split');
    if (!panel || !studio) return;

    if (forceState !== null) {
        isCanvasOpen = forceState;
    } else {
        isCanvasOpen = !isCanvasOpen;
    }

    if (isCanvasOpen) {
        if (typeof window.collapseSidebarForCanvas === 'function') {
            window.collapseSidebarForCanvas(true);
        }
        studio.classList.add('canvas-open');
        btn?.classList.add('bg-zinc-100', 'dark:bg-zinc-800', 'border-emerald-500/50');
        populateCanvas();
    } else {
        if (typeof window.collapseSidebarForCanvas === 'function') {
            window.collapseSidebarForCanvas(false);
        }
        studio.classList.remove('canvas-open');
        btn?.classList.remove('bg-zinc-100', 'dark:bg-zinc-800', 'border-emerald-500/50');
    }
}

function updateCanvasBadge() {
    const badge = document.getElementById('canvas-version-badge');
    if (badge) {
        badge.textContent = docVersions.length;
    }
}

export function setCanvasTab(tab) {
    currentCanvasTab = tab;
    ['preview', 'edit', 'diff'].forEach(t => {
        const btn = document.getElementById(`tab-btn-${t}`);
        const content = document.getElementById(`canvas-tab-${t}`);
        if (t === tab) {
            btn?.classList.add('bg-white', 'dark:bg-zinc-900', 'text-emerald-600', 'dark:text-emerald-400', 'shadow-2xs');
            btn?.classList.remove('text-zinc-400', 'hover:text-zinc-700', 'dark:hover:text-zinc-200');
            content?.classList.remove('hidden');
        } else {
            btn?.classList.remove('bg-white', 'dark:bg-zinc-900', 'text-emerald-600', 'dark:text-emerald-400', 'shadow-2xs');
            btn?.classList.add('text-zinc-400', 'hover:text-zinc-700', 'dark:hover:text-zinc-200');
            content?.classList.add('hidden');
        }
    });

    const configBtn = document.getElementById('tab-btn-config');
    const configContent = document.getElementById('canvas-tab-config');
    if (tab === 'config') {
        configBtn?.classList.add('bg-zinc-100', 'dark:bg-zinc-800', 'text-emerald-500', 'border-emerald-500/50');
        configBtn?.classList.remove('text-zinc-400');
        configContent?.classList.remove('hidden');
    } else {
        configBtn?.classList.remove('bg-zinc-100', 'dark:bg-zinc-800', 'text-emerald-500', 'border-emerald-500/50');
        configBtn?.classList.add('text-zinc-400');
        configContent?.classList.add('hidden');
    }

    if (tab === 'diff') {
        initDiffSelectors();
        renderDiffView();
    }
}

function populateCanvas() {
    const picker = document.getElementById('canvas-version-picker');
    picker.innerHTML = '';

    if (!docVersions.length) {
        picker.innerHTML = '<option value="">(Belum ada draf)</option>';
        document.getElementById('canvas-doc-type').textContent = 'CANVAS';
        document.getElementById('canvas-version-tag').textContent = 'v0';
        document.getElementById('canvas-status-tag').textContent = 'kosong';
        document.getElementById('canvas-preview-empty').classList.remove('hidden');
        document.getElementById('canvas-preview-content').classList.add('hidden');
        document.getElementById('canvas-editor-textarea').value = '';
        document.getElementById('canvas-action-buttons').innerHTML = '';
        return;
    }

    docVersions.forEach((v) => {
        const opt = document.createElement('option');
        opt.value = v.id;
        opt.textContent = `${v.doc_type.toUpperCase()} v${v.version} (${v.status})`;
        picker.appendChild(opt);
    });

    if (!activeVersion || !docVersions.some(v => v.id === activeVersion.id)) {
        activeVersion = docVersions[docVersions.length - 1];
    }
    picker.value = activeVersion.id;

    // Header info
    document.getElementById('canvas-doc-type').textContent = activeVersion.doc_type.toUpperCase();
    document.getElementById('canvas-version-tag').textContent = `v${activeVersion.version}`;
    const statusTag = document.getElementById('canvas-status-tag');
    statusTag.textContent = activeVersion.status;
    statusTag.className = 'text-[10px] font-mono px-1.5 py-0.5 rounded-full border uppercase ' + (
        activeVersion.status === 'approved' ? 'bg-emerald-500/15 text-emerald-500 border-emerald-500/30' :
        (activeVersion.status === 'rejected' ? 'bg-red-500/15 text-red-400 border-red-500/30' : 'text-zinc-400 border-zinc-300 dark:border-zinc-700')
    );
    document.getElementById('canvas-doc-subtitle').textContent = `Dibuat: ${new Date(activeVersion.created_at).toLocaleString('id-ID')}`;

    // Preview
    document.getElementById('canvas-preview-empty').classList.add('hidden');
    const prevContent = document.getElementById('canvas-preview-content');
    prevContent.classList.remove('hidden');
    prevContent.innerHTML = md(activeVersion.content_markdown);

    // Editor
    const ta = document.getElementById('canvas-editor-textarea');
    ta.value = activeVersion.content_markdown;
    ta.readOnly = (activeVersion.status !== 'draft');
    document.getElementById('btn-save-canvas-edit').disabled = (activeVersion.status !== 'draft');

    // Render Canvas Actions
    renderCanvasActionButtons();
}

export function switchCanvasVersion(versionId) {
    activeVersion = docVersions.find(v => v.id === versionId) || null;
    populateCanvas();
    if (currentCanvasTab === 'diff') {
        renderDiffView();
    }
}

function renderCanvasActionButtons() {
    const container = document.getElementById('canvas-action-buttons');
    container.innerHTML = '';
    if (!activeVersion) return;

    if (activeVersion.status === 'draft') {
        // Reject button
        const rejectBtn = document.createElement('button');
        rejectBtn.type = 'button';
        rejectBtn.className = 'px-3 py-1.5 border border-red-200 dark:border-red-900/60 hover:bg-red-50 dark:hover:bg-red-950/30 text-red-600 dark:text-red-400 rounded-lg text-xs font-medium transition-colors';
        rejectBtn.textContent = 'Minta Revisi';
        rejectBtn.onclick = async () => {
            try {
                await window.api(`/api/docs/versions/${activeVersion.id}/reject`, { method: 'POST' });
                window.toast('Draf ditandai untuk revisi.', 'info');
                await selectDocProject(docProjectId);
            } catch (err) {}
        };
        container.appendChild(rejectBtn);

        // Approve button
        const approveBtn = document.createElement('button');
        approveBtn.type = 'button';
        approveBtn.className = 'px-3.5 py-1.5 bg-black text-white dark:bg-white dark:text-black hover:opacity-90 rounded-lg text-xs font-semibold shadow-xs transition-opacity flex items-center gap-1.5';
        approveBtn.innerHTML = `<svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg> <span>Setujui Draf Ini</span>`;
        approveBtn.onclick = async () => {
            try {
                const res = await window.api(`/api/docs/versions/${activeVersion.id}/approve`, { method: 'POST' });
                window.toast(res.message, 'success');
                await selectDocProject(docProjectId);
            } catch (err) {}
        };
        container.appendChild(approveBtn);
    } else if (activeVersion.status === 'approved' && activeVersion.doc_type === 'sysdesign') {
        // Handoff to Schema Generator
        const handoffBtn = document.createElement('button');
        handoffBtn.type = 'button';
        handoffBtn.className = 'px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-semibold shadow-xs flex items-center gap-1.5 transition-colors';
        handoffBtn.innerHTML = `<svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg> <span>Buat Database Sekarang</span> <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>`;
        handoffBtn.onclick = async () => {
            if (!confirm('Lanjutkan rancangan ini untuk dibuatkan database otomatis? Folder proyek harus sudah terhubung.')) return;
            try {
                const res = await window.api(`/api/docs/versions/${activeVersion.id}/to-schema`, { method: 'POST' });
                window.toast(res.message, 'success');
                window.location.href = `/generations/${res.data.id}`;
            } catch (err) {}
        };
        container.appendChild(handoffBtn);
    }
}

export async function saveCanvasEdit() {
    if (!activeVersion || activeVersion.status !== 'draft') return;
    const content = document.getElementById('canvas-editor-textarea').value.trim();
    if (!content) {
        window.toast('Isi dokumen tidak boleh kosong.', 'warning');
        return;
    }
    try {
        await window.api(`/api/docs/versions/${activeVersion.id}`, {
            method: 'PUT',
            body: { content_markdown: content }
        });
        window.toast('Perubahan draf berhasil disimpan.', 'success');
        await selectDocProject(docProjectId);
    } catch (err) {}
}

// ---------------- TASK-1005: DIFF VIEW & EXPORT MARKDOWN ----------------
function initDiffSelectors() {
    const oldSel = document.getElementById('diff-version-old');
    const newSel = document.getElementById('diff-version-new');
    oldSel.innerHTML = '';
    newSel.innerHTML = '';

    docVersions.forEach((v) => {
        const opt1 = document.createElement('option');
        opt1.value = v.id;
        opt1.textContent = `${v.doc_type.toUpperCase()} v${v.version}`;
        oldSel.appendChild(opt1);

        const opt2 = document.createElement('option');
        opt2.value = v.id;
        opt2.textContent = `${v.doc_type.toUpperCase()} v${v.version}`;
        newSel.appendChild(opt2);
    });

    // Default: compare previous version with active version
    if (docVersions.length >= 2) {
        oldSel.value = docVersions[docVersions.length - 2].id;
        newSel.value = docVersions[docVersions.length - 1].id;
    } else if (docVersions.length === 1) {
        oldSel.value = docVersions[0].id;
        newSel.value = docVersions[0].id;
    }
}

// Zero-dependency LCS line diff (TASK-1005)
export function computeLineDiff(oldText, newText) {
    const oldLines = (oldText || '').split('\n');
    const newLines = (newText || '').split('\n');
    const N = oldLines.length;
    const M = newLines.length;

    // LCS table
    const dp = Array.from({ length: N + 1 }, () => new Int32Array(M + 1));
    for (let i = 0; i < N; i++) {
        for (let j = 0; j < M; j++) {
            if (oldLines[i] === newLines[j]) {
                dp[i + 1][j + 1] = dp[i][j] + 1;
            } else {
                dp[i + 1][j + 1] = Math.max(dp[i + 1][j], dp[i][j + 1]);
            }
        }
    }

    // Backtrack to build diff
    const result = [];
    let i = N, j = M;
    while (i > 0 || j > 0) {
        if (i > 0 && j > 0 && oldLines[i - 1] === newLines[j - 1]) {
            result.push({ type: 'same', text: oldLines[i - 1] });
            i--; j--;
        } else if (j > 0 && (i === 0 || dp[i][j - 1] >= dp[i - 1][j])) {
            result.push({ type: 'add', text: newLines[j - 1] });
            j--;
        } else if (i > 0 && (j === 0 || dp[i][j - 1] < dp[i - 1][j])) {
            result.push({ type: 'del', text: oldLines[i - 1] });
            i--;
        }
    }
    return result.reverse();
}

export function renderDiffView() {
    const oldId = document.getElementById('diff-version-old').value;
    const newId = document.getElementById('diff-version-new').value;
    const vOld = docVersions.find(v => v.id === oldId);
    const vNew = docVersions.find(v => v.id === newId);
    const out = document.getElementById('diff-output-box');
    const stats = document.getElementById('diff-summary-stats');

    if (!vOld || !vNew) {
        out.innerHTML = '<div class="text-zinc-400 italic">Pilih dua versi untuk membandingkan perubahan.</div>';
        stats.innerHTML = '';
        return;
    }

    const diff = computeLineDiff(vOld.content_markdown, vNew.content_markdown);
    let added = 0, deleted = 0;
    diff.forEach(d => {
        if (d.type === 'add') added++;
        if (d.type === 'del') deleted++;
    });

    stats.innerHTML = `
        <span class="text-emerald-500 font-semibold">+${added} baris baru</span>
        <span class="text-red-400 font-semibold">-${deleted} baris dihapus</span>
        <span>(${diff.length} total baris)</span>
    `;

    out.innerHTML = '';
    diff.forEach((d) => {
        const row = document.createElement('div');
        if (d.type === 'add') {
            row.className = 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 px-2 py-0.5 rounded border-l-2 border-emerald-500 flex gap-2 font-mono whitespace-pre-wrap';
            row.innerHTML = `<span class="select-none font-bold text-emerald-500">+</span><span>${esc(d.text) || '&nbsp;'}</span>`;
        } else if (d.type === 'del') {
            row.className = 'bg-red-500/10 text-red-600 dark:text-red-400 px-2 py-0.5 rounded border-l-2 border-red-500 flex gap-2 font-mono whitespace-pre-wrap line-through opacity-80';
            row.innerHTML = `<span class="select-none font-bold text-red-500">-</span><span>${esc(d.text) || '&nbsp;'}</span>`;
        } else {
            row.className = 'text-zinc-600 dark:text-zinc-400 px-2 py-0.5 flex gap-2 font-mono whitespace-pre-wrap';
            row.innerHTML = `<span class="select-none text-zinc-300 dark:text-zinc-700"> </span><span>${esc(d.text) || '&nbsp;'}</span>`;
        }
        out.appendChild(row);
    });
}

export function exportCurrentMarkdown() {
    if (!activeVersion || !activeVersion.content_markdown) {
        window.toast('Pilih draf dokumen untuk diunduh.', 'warning');
        return;
    }
    const blob = new Blob([activeVersion.content_markdown], { type: 'text/markdown;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    const safeTitle = (docProject?.title || 'dokumen').toLowerCase().replace(/[^a-z0-9]+/g, '_');
    a.href = url;
    a.download = `${safeTitle}_${activeVersion.doc_type}_v${activeVersion.version}.md`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    window.toast(`Dokumen ${activeVersion.doc_type.toUpperCase()} v${activeVersion.version} berhasil diunduh!`, 'success');
}

// ---------------- MODEL CATALOG & SELECTORS ----------------
export async function loadCatalog(refresh = false) {
    try {
        const res = await window.api(`/api/docs/models${refresh ? '?refresh=1' : ''}`);
        liveModels = res.data.live.models || [];
        const main = document.getElementById('doc-model');
        const comp = document.getElementById('doc-model-composer');
        const presets = res.data.presets || [];

        [main, comp].forEach(sel => {
            if (!sel) return;
            sel.innerHTML = '';

            // Group 1: Rekomendasi Utama
            const grpPresets = document.createElement('optgroup');
            grpPresets.label = '🌟 Rekomendasi Utama';
            presets.forEach((p) => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = `${p.label}${p.free ? ' (Gratis)' : ''}`;
                grpPresets.appendChild(opt);
            });
            sel.appendChild(grpPresets);

            // Group 2: Model Gratis Lainnya (:free)
            const freeOnly = liveModels.filter(m => m.id.endsWith(':free') && !presets.some(p => p.id === m.id));
            if (freeOnly.length > 0) {
                const grpFree = document.createElement('optgroup');
                grpFree.label = '🆓 Model Gratis / Bebas Kuota (:free)';
                freeOnly.slice(0, 20).forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m.id;
                    opt.textContent = m.id;
                    grpFree.appendChild(opt);
                });
                sel.appendChild(grpFree);
            }

            // Group 3: Model Berbayar / Populer Lainnya (termasuk GPT-OSS)
            const paidOrOther = liveModels.filter(m => !m.id.endsWith(':free') && !presets.some(p => p.id === m.id));
            if (paidOrOther.length > 0) {
                const grpOther = document.createElement('optgroup');
                grpOther.label = '🤖 Model Lanjutan & Penalaran Tinggi';
                paidOrOther.slice(0, 25).forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m.id;
                    opt.textContent = m.id;
                    grpOther.appendChild(opt);
                });
                sel.appendChild(grpOther);
            }
        });

        document.getElementById('model-meta').textContent = res.data.live.cached_at
            ? `Live catalog: ${liveModels.length} model • ${res.data.live.cached_at}`
            : 'Preset bawaan (offline/tanpa key)';

        syncModelPicker();
    } catch (e) {}
}

function syncModelPicker() {
    const val = docProject?.ai_model || 'nvidia/nemotron-3.5-lightning:free';
    const main = document.getElementById('doc-model');
    const comp = document.getElementById('doc-model-composer');

    [main, comp].forEach(sel => {
        if (!sel) return;
        if (val && ![...sel.options].some(o => o.value === val)) {
            const opt = document.createElement('option');
            opt.value = val;
            opt.textContent = val;
            sel.appendChild(opt);
        }
        sel.value = val;
    });
}

export async function changeDocModel(val) {
    if (!docProjectId) return;
    if (val.includes(':free') && !localStorage.getItem('devarchitect_free_warned')) {
        document.getElementById('privacy-modal').classList.remove('hidden');
    }
    try {
        const res = await window.api(`/api/docs/projects/${docProjectId}`, {
            method: 'PUT',
            body: { ai_model: val }
        });
        docProject = res.data;
        syncModelPicker();
        window.toast('Model AI diperbarui.', 'success');
    } catch (e) {}
}

export function ackPrivacy() {
    localStorage.setItem('devarchitect_free_warned', '1');
    document.getElementById('privacy-modal').classList.add('hidden');
}

export function useCustomModel() {
    const v = document.getElementById('doc-model-custom').value.trim();
    if (!v) return;
    changeDocModel(v);
}

export function refreshCatalog(force) {
    loadCatalog(force);
}

// ---------------- CODE PROJECT & SECTIONS ----------------
export async function loadCodeProjects() {
    try {
        const res = await window.api('/api/projects');
        const sel = document.getElementById('link-code-project');
        sel.innerHTML = '<option value="">— Belum ditautkan —</option>';
        (res.data || []).forEach((p) => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.project_name;
            sel.appendChild(opt);
        });
        syncCodeProjectPicker();
    } catch (e) {}
}

function syncCodeProjectPicker() {
    const sel = document.getElementById('link-code-project');
    if (sel && docProject?.code_project_id) {
        sel.value = docProject.code_project_id;
    }
}

export async function linkCodeProject(val) {
    if (!docProjectId) return;
    try {
        const res = await window.api(`/api/docs/projects/${docProjectId}`, {
            method: 'PUT',
            body: { code_project_id: val || null }
        });
        docProject = res.data;
        window.toast(val ? 'Proyek kode ditautkan.' : 'Tautan dilepas.', 'success');
    } catch (e) {
        syncCodeProjectPicker();
    }
}

export async function loadSectionsMap() {
    try {
        const res = await window.api('/api/docs/sections');
        sectionsMap = res.data || {};
        loadSections();
    } catch (e) {}
}

export function loadSections() {
    const type = document.getElementById('gen-doc-type')?.value || 'urd';
    const sel = document.getElementById('gen-section');
    if (!sel) return;
    sel.innerHTML = '<option value="">Full dokumen</option>';
    (sectionsMap[type] || []).forEach((s) => {
        const opt = document.createElement('option');
        opt.value = s;
        opt.textContent = s;
        sel.appendChild(opt);
    });
}

function syncGenType() {
    const map = { urd: 'urd', prd: 'prd', srs: 'srs', sysdesign: 'sysdesign' };
    const sel = document.getElementById('gen-doc-type');
    if (sel && map[docProject?.stage]) {
        sel.value = map[docProject.stage];
        loadSections();
    }
}

export async function generateDoc() {
    if (!docProjectId) { window.toast('Pilih proyek terlebih dahulu.', 'warning'); return; }
    if (typeof navigator !== 'undefined' && !navigator.onLine) {
        window.toast('Anda sedang offline. Dokumen lokal tetap bisa dibaca dan diedit di Canvas, namun generate dokumen AI memerlukan koneksi internet.', 'warning', 4000);
        return;
    }
    const docType = document.getElementById('gen-doc-type').value;
    const section = document.getElementById('gen-section').value || null;
    try {
        const res = await window.api(`/api/docs/projects/${docProjectId}/generate-doc`, {
            method: 'POST',
            body: { doc_type: docType, section },
        });
        pendingAssistantId = res.data.id;
        setBusy(true);
        const busyText = document.getElementById('chat-busy-text');
        if (busyText) busyText.textContent = `Menyusun ${docType.toUpperCase()}${section ? ' — ' + section : ''}...`;
        await selectDocProject(docProjectId);
        clearInterval(pollTimer);
        pollTimer = setInterval(pollAssistant, 2000);
    } catch (e) {}
}

// ---------------- SNAPSHOT & NEW PROJECT MODAL ----------------
export function openNewDocModal() {
    document.getElementById('newdoc-modal').classList.remove('hidden');
    document.getElementById('newdoc-title').focus();
}
export function closeNewDocModal() {
    document.getElementById('newdoc-modal').classList.add('hidden');
}

export function useBriefTemplate(i) {
    openNewDocModal();
    document.getElementById('newdoc-desc').value = BRIEF_TEMPLATES[i] || '';
    document.getElementById('newdoc-title').focus();
}

export async function createDocProject() {
    const title = document.getElementById('newdoc-title').value.trim();
    const description = document.getElementById('newdoc-desc').value.trim();
    if (title.length < 3) { window.toast('Judul proyek minimal 3 karakter.', 'warning'); return; }
    try {
        const res = await window.api('/api/docs/projects', { method: 'POST', body: { title, description } });
        closeNewDocModal();
        document.getElementById('newdoc-title').value = '';
        document.getElementById('newdoc-desc').value = '';
        window.toast('Proyek dokumen berhasil dibuat.', 'success');
        await loadDocProjects(res.data.id);
    } catch (e) {}
}

export function openSnapshotModal() {
    if (!docProjectId) { window.toast('Pilih proyek dokumen dulu.', 'warning'); return; }
    document.getElementById('snapshot-modal').classList.remove('hidden');
}
export function closeSnapshotModal() {
    document.getElementById('snapshot-modal').classList.add('hidden');
}

export function fillFromLastAssistant() {
    const last = [...(docProject?.messages || [])].reverse().find((m) => m.role === 'assistant' && m.content);
    if (last) {
        document.getElementById('snapshot-content').value = last.content;
    } else {
        window.toast('Belum ada balasan dari Asisten.', 'warning');
    }
}

export async function saveSnapshot() {
    const content = document.getElementById('snapshot-content').value.trim();
    if (!content) { window.toast('Isi draf masih kosong.', 'warning'); return; }
    try {
        const res = await window.api(`/api/docs/projects/${docProjectId}/versions`, {
            method: 'POST',
            body: { doc_type: document.getElementById('snapshot-type').value, content_markdown: content },
        });
        window.toast(res.message, 'success');
        document.getElementById('snapshot-content').value = '';
        closeSnapshotModal();
        await selectDocProject(docProjectId);
        toggleCanvas(true);
    } catch (e) {}
}

export function toggleProjectMenu() {
    const pop = document.getElementById('project-menu-pop');
    pop.classList.toggle('hidden');
}

export async function archiveDocProject() {
    if (!docProjectId) return;
    try {
        const res = await window.api(`/api/docs/projects/${docProjectId}/archive`, { method: 'POST' });
        window.toast(res.message, 'success');
        document.getElementById('project-menu-pop').classList.add('hidden');
        await loadDocProjects(docProjectId);
    } catch (e) {}
}

export async function deleteDocProject() {
    if (!docProjectId || !confirm('Hapus permanen proyek dokumen ini beserta seluruh pesan & versi?')) return;
    try {
        await window.api(`/api/docs/projects/${docProjectId}`, { method: 'DELETE' });
        docProjectId = null;
        docProject = null;
        window.toast('Proyek berhasil dihapus.', 'success');
        document.getElementById('project-menu-pop').classList.add('hidden');
        await loadDocProjects();
        toggleCanvas(false);
    } catch (e) {}
}

// ---------------- EXPOSE GLOBALS TO WINDOW (FOR BLADE ONCLICK) ----------------
window.esc = esc;
window.md = md;
window.autoExpandTextarea = autoExpandTextarea;
window.handleChatKeydown = handleChatKeydown;
window.copyCodeBlock = copyCodeBlock;
window.copyMessageText = copyMessageText;
window.snapshotFromMessage = snapshotFromMessage;
window.renderStagePipeline = renderStagePipeline;
window.loadDocProjects = loadDocProjects;
window.selectDocProject = selectDocProject;
window.sendChat = sendChat;
window.sendQuickPrompt = sendQuickPrompt;
window.cancelAssistant = cancelAssistant;
window.toggleCanvas = toggleCanvas;
window.setCanvasTab = setCanvasTab;
window.switchCanvasVersion = switchCanvasVersion;
window.saveCanvasEdit = saveCanvasEdit;
window.computeLineDiff = computeLineDiff;
window.renderDiffView = renderDiffView;
window.exportCurrentMarkdown = exportCurrentMarkdown;
window.changeDocModel = changeDocModel;
window.useCustomModel = useCustomModel;
window.refreshCatalog = refreshCatalog;
window.linkCodeProject = linkCodeProject;
window.loadSections = loadSections;
window.generateDoc = generateDoc;
window.openNewDocModal = openNewDocModal;
window.closeNewDocModal = closeNewDocModal;
window.useBriefTemplate = useBriefTemplate;
window.createDocProject = createDocProject;
window.openSnapshotModal = openSnapshotModal;
window.closeSnapshotModal = closeSnapshotModal;
window.fillFromLastAssistant = fillFromLastAssistant;
window.saveSnapshot = saveSnapshot;
window.toggleProjectMenu = toggleProjectMenu;
window.archiveDocProject = archiveDocProject;
window.deleteDocProject = deleteDocProject;
window.ackPrivacy = ackPrivacy;
window.loadOlderMessages = loadOlderMessages;
window.createDashboardProject = createDashboardProject;
window.openPromoteDashboardModal = openPromoteDashboardModal;
window.closePromoteDashboardModal = closePromoteDashboardModal;
window.archiveDocChatSession = archiveDocChatSession;
window.exportDocTranscript = exportDocTranscript;

// Click outside handler for project menu
document.addEventListener('click', (e) => {
    const btn = document.getElementById('btn-project-menu');
    const pop = document.getElementById('project-menu-pop');
    if (btn && pop && !btn.contains(e.target) && !pop.contains(e.target)) {
        pop.classList.add('hidden');
    }
});

// ---------------- INITIALIZATION ----------------
export async function initAssistant() {
    while (typeof window.api !== 'function') {
        await new Promise((r) => setTimeout(r, 20));
    }

    // Composer select change listener
    const compSel = document.getElementById('doc-model-composer');
    if (compSel) {
        compSel.addEventListener('change', (e) => {
            changeDocModel(e.target.value);
        });
    }

    await loadCatalog(false);
    await loadSectionsMap();
    await loadCodeProjects();
    await loadDocProjects();

    const input = document.getElementById('chat-input');
    if (input) {
        autoExpandTextarea(input);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAssistant);
} else {
    initAssistant();
}
