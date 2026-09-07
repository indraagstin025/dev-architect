/**
 * TASK-702/703/704 — ERD viewer + code review (dibundel Vite, offline-first).
 * Dipakai hanya di halaman review generasi. Mermaid & highlight.js lokal.
 */
import mermaid from 'mermaid';
import hljs from 'highlight.js/lib/core';
import php from 'highlight.js/lib/languages/php';
import sql from 'highlight.js/lib/languages/sql';
import typescript from 'highlight.js/lib/languages/typescript';
import java from 'highlight.js/lib/languages/java';
import json from 'highlight.js/lib/languages/json';
import plaintext from 'highlight.js/lib/languages/plaintext';
import 'highlight.js/styles/github-dark.css';

hljs.registerLanguage('php', php);
hljs.registerLanguage('sql', sql);
hljs.registerLanguage('typescript', typescript);
hljs.registerLanguage('java', java);
hljs.registerLanguage('json', json);
hljs.registerLanguage('plaintext', plaintext);

function getMermaidTheme() {
    const isDark = document.documentElement.classList.contains('dark');
    return {
        darkMode: isDark,
        background: isDark ? '#000000' : '#ffffff',
        primaryColor: isDark ? '#18181b' : '#f4f4f5',
        primaryTextColor: isDark ? '#f4f4f5' : '#09090b',
        primaryBorderColor: isDark ? '#3f3f46' : '#d4d4d8',
        lineColor: isDark ? '#71717a' : '#71717a',
        secondaryColor: isDark ? '#27272a' : '#e4e4e7',
        tertiaryColor: isDark ? '#09090b' : '#fafafa',
        mainBkg: isDark ? '#0c0c0e' : '#ffffff',
        nodeBorder: isDark ? '#3f3f46' : '#d4d4d8',
        nodeTextColor: isDark ? '#f4f4f5' : '#09090b',
        attributeBackgroundColorOdd: isDark ? '#18181b' : '#f4f4f5',
        attributeBackgroundColorEven: isDark ? '#0c0c0e' : '#ffffff',
    };
}

mermaid.initialize({
    startOnLoad: false,
    theme: 'base',
    themeVariables: getMermaidTheme(),
    securityLevel: 'strict',
    er: { useMaxWidth: false },
});

const EXT_LANG = { php: 'php', sql: 'sql', ts: 'typescript', java: 'java', json: 'json', prisma: 'plaintext' };

function langFor(filename) {
    const ext = (filename.split('.').pop() || '').toLowerCase();
    const lang = EXT_LANG[ext] || 'plaintext';
    return hljs.getLanguage(lang) ? lang : 'plaintext';
}

function download(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 2000);
}

const Viewer = {
    cfg: null,
    files: [],
    activeIndex: 0,
    editing: false,
    scale: 1,
    panX: 0,
    panY: 0,

    init(cfg) {
        this.cfg = cfg;
        this.files = (cfg.files || []).map((f) => ({ filename: f.filename, content: f.content || '' }));
        this.renderFileList();
        this.selectFile(0);
        this.renderDiagram(cfg.erd || '');
        this.renderWarnings(cfg.warnings || []);
        this.initPan();
    },

    // ---- ERD canvas ----
    async renderDiagram(text) {
        const box = document.getElementById('erd-canvas');
        const err = document.getElementById('erd-error');
        if (!text || !text.trim()) {
            box.innerHTML = '<p class="text-xs text-zinc-500 italic p-4">AI tidak mengembalikan diagram ERD.</p>';
            return;
        }
        try {
            const clean = text.replace(/^```(?:mermaid)?\s*/i, '').replace(/\s*```$/, '').trim();
            // Re-initialize theme in case it switched
            mermaid.initialize({
                startOnLoad: false,
                theme: 'base',
                themeVariables: getMermaidTheme(),
                securityLevel: 'strict',
                er: { useMaxWidth: false },
            });
            const { svg } = await mermaid.render('erdDiagram' + Date.now(), clean);
            box.innerHTML = svg;
            err.classList.add('hidden');
            this.applyTransform();
        } catch (e) {
            box.innerHTML = '';
            err.classList.remove('hidden');
            err.querySelector('[data-erd-raw]').textContent = text;
        }
    },

    applyTransform() {
        const inner = document.getElementById('erd-inner');
        if (!inner) return;
        inner.style.transform = `translate(${this.panX}px, ${this.panY}px) scale(${this.scale})`;
        const label = document.getElementById('zoom-label');
        if (label) label.textContent = `${Math.round(this.scale * 100)}%`;
    },

    zoomIn() { this.scale = Math.min(3, +(this.scale + 0.15).toFixed(2)); this.applyTransform(); },
    zoomOut() { this.scale = Math.max(0.3, +(this.scale - 0.15).toFixed(2)); this.applyTransform(); },
    zoomReset() { this.scale = 1; this.panX = 0; this.panY = 0; this.applyTransform(); },

    initPan() {
        const viewport = document.getElementById('erd-viewport');
        if (!viewport) return;
        let dragging = false, sx = 0, sy = 0, bx = 0, by = 0;
        viewport.addEventListener('pointerdown', (e) => {
            if (e.target.closest('button')) return;
            dragging = true; sx = e.clientX; sy = e.clientY; bx = this.panX; by = this.panY;
            viewport.setPointerCapture(e.pointerId);
            viewport.style.cursor = 'grabbing';
        });
        viewport.addEventListener('pointermove', (e) => {
            if (!dragging) return;
            this.panX = bx + (e.clientX - sx); this.panY = by + (e.clientY - sy);
            this.applyTransform();
        });
        ['pointerup', 'pointercancel'].forEach((ev) => viewport.addEventListener(ev, () => {
            dragging = false; viewport.style.cursor = 'grab';
        }));
        viewport.addEventListener('wheel', (e) => {
            if (!e.ctrlKey) return;
            e.preventDefault();
            (e.deltaY < 0 ? this.zoomIn() : this.zoomOut());
        }, { passive: false });
    },

    exportSVG() {
        const svg = document.querySelector('#erd-canvas svg');
        if (!svg) { window.toast('Belum ada diagram untuk diekspor.', 'warning'); return; }
        const xml = new XMLSerializer().serializeToString(svg);
        download(new Blob([xml], { type: 'image/svg+xml;charset=utf-8' }), 'erd-diagram.svg');
        window.toast('ERD diekspor sebagai SVG.', 'success');
    },

    exportPNG() {
        const svg = document.querySelector('#erd-canvas svg');
        if (!svg) { window.toast('Belum ada diagram untuk diekspor.', 'warning'); return; }
        const xml = new XMLSerializer().serializeToString(svg);
        const url = URL.createObjectURL(new Blob([xml], { type: 'image/svg+xml;charset=utf-8' }));
        const img = new Image();
        img.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = img.width * 2;
            canvas.height = img.height * 2;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = document.documentElement.classList.contains('dark') ? '#0c0c0e' : '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            URL.revokeObjectURL(url);
            canvas.toBlob((blob) => {
                if (blob) { download(blob, 'erd-diagram.png'); window.toast('ERD diekspor sebagai PNG.', 'success'); }
            }, 'image/png');
        };
        img.onerror = () => { URL.revokeObjectURL(url); window.toast('Gagal meraster diagram.', 'error'); };
        img.src = url;
    },

    // ---- File tabs + editor ----
    renderFileList() {
        const list = document.getElementById('file-tabs');
        list.innerHTML = '';
        this.files.forEach((f, i) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.dataset.index = i;
            btn.className = 'file-tab w-full text-left px-3 py-1.5 rounded-md text-xs font-mono truncate transition-colors';
            btn.textContent = f.filename;
            btn.addEventListener('click', () => this.selectFile(i));
            list.appendChild(btn);
        });
        this.paintTabs();
    },

    paintTabs() {
        document.querySelectorAll('.file-tab').forEach((el) => {
            const on = Number(el.dataset.index) === this.activeIndex;
            el.className = `file-tab w-full text-left px-3 py-1.5 rounded-md text-xs font-mono truncate transition-colors ${on ? 'bg-zinc-100 dark:bg-zinc-900 text-zinc-900 dark:text-white font-medium border border-zinc-300 dark:border-zinc-800 shadow-xs' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-900/60 border border-transparent'}`;
        });
        const name = document.getElementById('active-filename');
        if (name && this.files[this.activeIndex]) name.textContent = this.files[this.activeIndex].filename;
    },

    selectFile(i) {
        if (!this.files[i]) return;
        this.syncEditorToState();
        this.activeIndex = i;
        this.editing = false;
        this.paintTabs();
        this.renderCode();
    },

    syncEditorToState() {
        const ta = document.getElementById('code-editor');
        if (ta && this.files[this.activeIndex]) {
            this.files[this.activeIndex].content = ta.value;
        }
    },

    renderCode() {
        const f = this.files[this.activeIndex];
        const view = document.getElementById('code-view');
        const edit = document.getElementById('code-edit-wrap');
        if (!f) { view.innerHTML = ''; return; }
        if (this.editing) {
            view.classList.add('hidden'); edit.classList.remove('hidden');
            document.getElementById('code-editor').value = f.content;
        } else {
            edit.classList.add('hidden'); view.classList.remove('hidden');
            view.innerHTML = '';
            const pre = document.createElement('pre');
            pre.className = 'text-xs leading-relaxed overflow-auto max-h-[520px] rounded-xl';
            const code = document.createElement('code');
            code.className = `language-${langFor(f.filename)} hljs rounded-xl`;
            code.textContent = f.content;
            pre.appendChild(code);
            view.appendChild(pre);
            hljs.highlightElement(code);
        }
        document.getElementById('btn-edit-toggle').textContent = this.editing ? 'Batal Edit' : 'Edit Kode';
        document.getElementById('btn-save-draft').classList.toggle('hidden', !this.editing);
    },

    toggleEdit() {
        this.editing = !this.editing;
        this.renderCode();
        if (this.editing) document.getElementById('code-editor').focus();
    },

    payload() {
        this.syncEditorToState();
        return this.files.map((f) => ({ filename: f.filename, content: f.content }));
    },

    async saveDraft() {
        const btn = document.getElementById('btn-save-draft');
        btn.disabled = true;
        try {
            await window.api(`/api/generations/${this.cfg.generationId}`, {
                method: 'PUT',
                body: { migration_files: this.payload() },
            });
            window.toast('Perubahan draf berhasil disimpan.', 'success');
            this.editing = false;
            this.renderCode();
        } catch (e) { /* toast otomatis */ }
        finally { btn.disabled = false; }
    },

    async inject(force = false) {
        const btn = document.getElementById('btn-confirm-inject');
        if (btn) btn.disabled = true;
        try {
            const res = await window.api(`/api/generations/${this.cfg.generationId}/inject`, {
                method: 'POST',
                body: { force },
            });
            window.toast(res.message || 'File berhasil disimpan ke folder proyek.', 'success');
            document.getElementById('inject-modal')?.classList.add('hidden');
            const resultBox = document.getElementById('inject-result');
            if (resultBox) {
                resultBox.classList.remove('hidden');
                const ul = resultBox.querySelector('ul');
                if (ul) {
                    ul.innerHTML = '';
                    (res.data?.written_files || []).forEach((f) => {
                        const li = document.createElement('li');
                        li.className = 'font-mono';
                        li.textContent = f;
                        ul.appendChild(li);
                    });
                }
            }
            const statusBadge = document.getElementById('status-badge');
            if (statusBadge) {
                statusBadge.textContent = 'Tersimpan ke Proyek';
                statusBadge.className = 'px-2.5 py-1 rounded-md text-xs font-medium bg-emerald-500/20 text-emerald-600 dark:text-[#3ECF8E] border border-emerald-500/40';
            }
            const injectBtn = document.getElementById('btn-inject');
            if (injectBtn) {
                injectBtn.textContent = 'Simpan Ulang ke Proyek';
            }
        } catch (e) {
            /* toast otomatis dari window.api */
        } finally {
            if (btn) btn.disabled = false;
        }
    },

    renderWarnings(warnings) {
        const box = document.getElementById('lint-warnings');
        if (!box) return;
        if (!warnings || !warnings.length) { box.classList.add('hidden'); return; }
        box.classList.remove('hidden');
        const ul = box.querySelector('ul');
        ul.innerHTML = '';
        warnings.forEach((w) => {
            const li = document.createElement('li');
            li.textContent = w;
            ul.appendChild(li);
        });
    },
};

window.addEventListener('theme-changed', () => {
    if (Viewer.cfg && Viewer.cfg.erd) {
        Viewer.renderDiagram(Viewer.cfg.erd);
    }
});

window.ErdViewer = Viewer;
export default Viewer;
