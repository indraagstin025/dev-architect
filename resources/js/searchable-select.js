/**
 * Searchable dropdown generik (vanilla, tanpa dependensi).
 * Membungkus <select> existing: select di-hide, custom UI sinkron dua arah
 * sehingga handler onchange lama tetap berjalan tanpa perubahan.
 *
 * window.attachSearchable(selectEl, {
 *   searchPlaceholder: 'Cari...',
 *   groupOf: (option) => 'Nama Grup',       // opsional
 *   renderLabel: (option) => string,        // opsional, default option.text
 *   badgeOf: (option) => string|null,       // opsional, mis. 'Gratis'
 * })
 */
window.attachSearchable = function (selectEl, opts = {}) {
    if (!selectEl || selectEl.dataset.searchable === '1') return null;
    selectEl.dataset.searchable = '1';

    const cfg = {
        searchPlaceholder: opts.searchPlaceholder || 'Ketik untuk mencari...',
        groupOf: opts.groupOf || null,
        renderLabel: opts.renderLabel || ((opt) => opt.text),
        badgeOf: opts.badgeOf || null,
    };

    const host = document.createElement('div');
    host.className = 'ss-host relative';
    selectEl.classList.add('hidden');
    selectEl.after(host);

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'ss-btn w-full flex items-center gap-2 bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-lg px-2.5 py-1.5 text-xs text-left truncate';
    host.appendChild(btn);

    const pop = document.createElement('div');
    pop.className = 'ss-pop hidden absolute z-50 left-0 right-0 mt-1 max-h-64 overflow-y-auto bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg shadow-2xl p-1.5 space-y-0.5';
    host.appendChild(pop);

    const search = document.createElement('input');
    search.type = 'text';
    search.placeholder = cfg.searchPlaceholder;
    search.className = 'w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-lg px-2.5 py-1.5 text-xs mb-1';
    pop.appendChild(search);

    const list = document.createElement('div');
    pop.appendChild(list);

    let items = [];
    let activeIdx = -1;

    function readOptions() {
        items = [...selectEl.options].map((o) => ({
            value: o.value,
            text: o.text,
            group: cfg.groupOf ? cfg.groupOf(o) : '',
        }));
    }

    function paintButton() {
        const cur = selectEl.options[selectEl.selectedIndex];
        btn.innerHTML = '';
        const label = document.createElement('span');
        label.className = 'flex-1 truncate font-mono';
        label.textContent = cur ? cfg.renderLabel(cur) : '—';
        btn.appendChild(label);
        if (cur && cfg.badgeOf) {
            const b = cfg.badgeOf(cur);
            if (b) {
                const badge = document.createElement('span');
                badge.className = 'text-[10px] font-bold px-1.5 py-0.5 rounded bg-emerald-500/15 text-emerald-500 border border-emerald-500/30 shrink-0';
                badge.textContent = b;
                btn.appendChild(badge);
            }
        }
        const caret = document.createElement('span');
        caret.className = 'text-zinc-400 shrink-0';
        caret.textContent = '▾';
        btn.appendChild(caret);
    }

    function filtered() {
        const q = search.value.trim().toLowerCase();
        if (!q) return items;
        return items.filter((i) => (i.text + ' ' + i.value).toLowerCase().includes(q));
    }

    function paintList() {
        list.innerHTML = '';
        const rows = filtered();
        let lastGroup = null;
        activeIdx = rows.length ? 0 : -1;
        if (!rows.length) {
            const empty = document.createElement('div');
            empty.className = 'px-2.5 py-2 text-xs text-zinc-500 italic';
            empty.textContent = 'Tidak ada hasil.';
            list.appendChild(empty);
            return;
        }
        rows.forEach((item, i) => {
            if (item.group && item.group !== lastGroup) {
                lastGroup = item.group;
                const g = document.createElement('div');
                g.className = 'px-2.5 pt-1.5 pb-0.5 text-[10px] font-bold uppercase tracking-wider text-zinc-500 font-mono';
                g.textContent = lastGroup;
                list.appendChild(g);
            }
            const fakeOpt = { value: item.value, text: item.text };
            const row = document.createElement('button');
            row.type = 'button';
            row.dataset.idx = i;
            row.className = 'ss-row w-full flex items-center gap-2 text-left px-2.5 py-1.5 rounded-lg text-xs truncate';
            const label = document.createElement('span');
            label.className = 'flex-1 truncate font-mono';
            label.textContent = item.text;
            row.appendChild(label);
            if (cfg.badgeOf) {
                const b = cfg.badgeOf(fakeOpt);
                if (b) {
                    const badge = document.createElement('span');
                    badge.className = 'text-[10px] font-bold px-1.5 py-0.5 rounded bg-emerald-500/15 text-emerald-500 border border-emerald-500/30 shrink-0';
                    badge.textContent = b;
                    row.appendChild(badge);
                }
            }
            if (item.value === selectEl.value) {
                row.classList.add('bg-zinc-100', 'dark:bg-zinc-800');
            }
            row.addEventListener('click', () => pick(item.value));
            list.appendChild(row);
        });
        paintActive();
    }

    function paintActive() {
        list.querySelectorAll('.ss-row').forEach((row) => {
            const on = Number(row.dataset.idx) === activeIdx;
            row.classList.toggle('ring-1', on);
            row.classList.toggle('ring-emerald-500', on);
        });
    }

    function pick(value) {
        if (selectEl.value !== value) {
            selectEl.value = value;
            selectEl.dispatchEvent(new Event('change', { bubbles: true }));
        }
        paintButton();
        close();
    }

    function open() {
        readOptions();
        paintList();
        pop.classList.remove('hidden');
        search.value = '';
        paintList();
        search.focus();
        document.addEventListener('click', outside, true);
    }

    function close() {
        pop.classList.add('hidden');
        document.removeEventListener('click', outside, true);
    }

    function outside(e) {
        if (!host.contains(e.target)) close();
    }

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (pop.classList.contains('hidden')) open();
        else close();
    });

    search.addEventListener('input', paintList);
    search.addEventListener('keydown', (e) => {
        const rows = filtered();
        if (e.key === 'ArrowDown') { e.preventDefault(); activeIdx = Math.min(rows.length - 1, activeIdx + 1); paintActive(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); activeIdx = Math.max(0, activeIdx - 1); paintActive(); }
        else if (e.key === 'Enter' && rows[activeIdx]) { e.preventDefault(); pick(rows[activeIdx].value); }
        else if (e.key === 'Escape') { close(); btn.focus(); }
    });

    // Sinkron saat select diubah programatik (mis. opsi ditambah lalu value diset)
    new MutationObserver(() => { readOptions(); paintButton(); })
        .observe(selectEl, { childList: true, attributes: true, attributeFilter: ['value'] });
    selectEl.addEventListener('change', paintButton);

    readOptions();
    paintButton();

    return {
        refresh() { readOptions(); paintButton(); if (!pop.classList.contains('hidden')) paintList(); },
        element: host,
    };
};
