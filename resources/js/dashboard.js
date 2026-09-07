/**
 * DEVArchitect - Dashboard Project Manager & Scaffold UI
 * Modular frontend script for project management, view switching, searching, and scaffolding.
 */

let pendingDeleteId = null;

// Card Menu & Editor Popover Handling (Supabase Style)
window.toggleCardMenu = function(menuId, event) {
    if (event) {
        event.stopPropagation();
    }
    const target = document.getElementById(menuId);
    if (!target) return;
    const isHidden = target.classList.contains('hidden');
    document.querySelectorAll('.card-popover').forEach(el => el.classList.add('hidden'));
    if (isHidden) {
        target.classList.remove('hidden');
    }
};

document.addEventListener('click', (e) => {
    if (!e.target.closest('.card-popover-wrapper')) {
        document.querySelectorAll('.card-popover').forEach(el => el.classList.add('hidden'));
    }
});

// Supabase View Mode Switcher: Grid vs List
window.switchProjectView = function(mode) {
    const gridContainer = document.getElementById('projects-grid-container');
    const listContainer = document.getElementById('projects-list-container');
    const btnGrid = document.getElementById('btn-view-grid');
    const btnList = document.getElementById('btn-view-list');

    if (!gridContainer || !listContainer) return;

    if (mode === 'list') {
        gridContainer.classList.add('hidden');
        listContainer.classList.remove('hidden');
        if (btnList) btnList.className = 'p-1.5 rounded text-zinc-900 dark:text-white bg-white dark:bg-zinc-800 shadow-2xs transition-colors';
        if (btnGrid) btnGrid.className = 'p-1.5 rounded text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 transition-colors';
    } else {
        listContainer.classList.add('hidden');
        gridContainer.classList.remove('hidden');
        if (btnGrid) btnGrid.className = 'p-1.5 rounded text-zinc-900 dark:text-white bg-white dark:bg-zinc-800 shadow-2xs transition-colors';
        if (btnList) btnList.className = 'p-1.5 rounded text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 transition-colors';
    }
    localStorage.setItem('devarchitect_project_view', mode);
};

// Supabase Search, Filter & Sort
window.filterAndSortProjects = function() {
    const searchInput = document.getElementById('project-search-input');
    const statusFilter = document.getElementById('project-status-filter');
    const sortFilter = document.getElementById('project-sort-filter');

    const searchVal = (searchInput ? searchInput.value : '').toLowerCase().trim();
    const statusVal = statusFilter ? statusFilter.value : 'all';
    const sortVal = sortFilter ? sortFilter.value : 'name_asc';

    const containers = ['projects-grid-container', 'projects-list-container'];
    let visibleCount = 0;

    containers.forEach(containerId => {
        const container = document.getElementById(containerId);
        if (!container) return;

        const items = Array.from(container.querySelectorAll('.project-item'));

        items.forEach(item => {
            const name = (item.getAttribute('data-name') || '').toLowerCase();
            const path = (item.getAttribute('data-path') || '').toLowerCase();
            const status = item.getAttribute('data-status') || '';

            const matchesSearch = !searchVal || name.includes(searchVal) || path.includes(searchVal);
            const matchesStatus = (statusVal === 'all') || (status === statusVal);

            if (matchesSearch && matchesStatus) {
                item.classList.remove('hidden');
                if (containerId === 'projects-grid-container') visibleCount++;
            } else {
                item.classList.add('hidden');
            }
        });

        // Sorting
        items.sort((a, b) => {
            if (sortVal === 'name_asc') {
                return (a.getAttribute('data-name') || '').localeCompare(b.getAttribute('data-name') || '');
            } else if (sortVal === 'name_desc') {
                return (b.getAttribute('data-name') || '').localeCompare(a.getAttribute('data-name') || '');
            } else if (sortVal === 'updated_desc') {
                return parseInt(b.getAttribute('data-updated') || 0) - parseInt(a.getAttribute('data-updated') || 0);
            }
            return 0;
        });

        items.forEach(item => container.appendChild(item));
    });

    const badge = document.getElementById('project-count-badge');
    if (badge) {
        badge.textContent = `${visibleCount} proyek`;
    }

    const noResults = document.getElementById('projects-no-results');
    if (noResults) {
        noResults.classList.toggle('hidden', visibleCount > 0);
    }
};

// Initialize view mode on load
document.addEventListener('DOMContentLoaded', () => {
    const savedView = localStorage.getItem('devarchitect_project_view') || 'grid';
    window.switchProjectView(savedView);
});

window.openAddModal = function() {
    window.switchAddTab('existing');
    document.getElementById('add-project-modal').classList.remove('hidden');
};

window.pickExistingFolder = async function() {
    const btn = document.getElementById('btn-pick-existing');
    if (btn) btn.disabled = true;

    try {
        let pickedPath = null;
        if (typeof window.desktopPickFolder === 'function') {
            const tauriPick = await window.desktopPickFolder();
            if (tauriPick) {
                if (tauriPick.cancelled) {
                    if (btn) btn.disabled = false;
                    return;
                }
                pickedPath = tauriPick.path;
            }
        }

        const result = await window.api('/api/projects/browse', { 
            method: 'POST',
            body: pickedPath ? { path: pickedPath } : {}
        });

        if (result.cancelled) {
            return;
        }

        if (!result.valid) {
            window.toast(result.message || 'Folder yang dipilih bukan proyek yang valid.', 'warning');
            return;
        }

        document.getElementById('modal_project_name').value = result.project_name || '';
        document.getElementById('modal_absolute_path').value = result.path || '';
        if (result.framework) {
            document.getElementById('modal_framework_type').value = result.framework;
        }
        if (result.dialect && document.getElementById('modal_database_dialect')) {
            document.getElementById('modal_database_dialect').value = result.dialect;
        }
        document.getElementById('modal_detection_msg').textContent = `Terdeteksi: ${result.message}`;
        const dialectMsgEl = document.getElementById('modal_dialect_msg');
        if (dialectMsgEl) {
            dialectMsgEl.textContent = result.dialect_label ? `Dialek terdeteksi: ${result.dialect_label}` : '';
        }

        window.switchAddTab('existing');
        document.getElementById('add-project-modal').classList.remove('hidden');
    } catch (err) {
        // Handled via window.api toast
    } finally {
        if (btn) btn.disabled = false;
    }
};

window.closeAddModal = function() {
    document.getElementById('add-project-modal').classList.add('hidden');
};

// ---- Tab Scaffold: Buat Proyek Baru (TASK-607) ----
let scaffoldJobId = null;
let scaffoldTimer = null;

window.switchAddTab = function(which) {
    const existing = which === 'existing';
    document.getElementById('tab-existing').classList.toggle('hidden', !existing);
    document.getElementById('tab-scaffold').classList.toggle('hidden', existing);
    const on = 'py-1.5 rounded-md bg-white dark:bg-zinc-950 text-zinc-900 dark:text-white shadow-xs';
    const off = 'py-1.5 rounded-md text-zinc-500 dark:text-zinc-400';
    document.getElementById('tab-btn-existing').className = existing ? on : off;
    document.getElementById('tab-btn-scaffold').className = existing ? off : on;
    if (!existing) {
        window.toggleSpringOptions();
        window.loadScaffoldPrereq();
    }
};

window.selectScaffoldTemplate = function(template) {
    const input = document.getElementById('scaffold_template');
    if (input) input.value = template;

    // Perbarui status aktif pada kartu pilihan framework
    const cards = document.querySelectorAll('.scaffold-fw-card');
    cards.forEach(card => {
        const isMatch = card.getAttribute('data-template') === template;
        const check = card.querySelector('.fw-check');
        if (isMatch) {
            card.className = 'scaffold-fw-card p-2.5 rounded-xl border border-zinc-900 dark:border-white bg-zinc-100/70 dark:bg-zinc-900/60 transition-all flex flex-col justify-between text-left cursor-pointer group relative shadow-xs';
            if (check) check.classList.remove('opacity-0');
        } else {
            card.className = 'scaffold-fw-card p-2.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 transition-all flex flex-col justify-between text-left cursor-pointer group relative shadow-xs hover:border-zinc-400 dark:hover:border-zinc-700';
            if (check) check.classList.add('opacity-0');
        }
    });

    window.toggleSpringOptions();
    window.updateScaffoldPrereqUI();
};

window.toggleSpringOptions = function() {
    const templateInput = document.getElementById('scaffold_template');
    const isSpring = templateInput ? templateInput.value === 'springboot_hibernate' : false;
    const springOpts = document.getElementById('spring-options');
    if (springOpts) springOpts.classList.toggle('hidden', !isSpring);
};

window.loadScaffoldPrereq = async function() {
    const badgesBox = document.getElementById('scaffold-tool-badges');
    if (badgesBox) {
        badgesBox.innerHTML = `<span class="text-[11px] text-zinc-400 animate-pulse">Memeriksa kelengkapan tool di komputer...</span>`;
    }
    try {
        const res = await window.api('/api/scaffold/prerequisites');
        window.scaffoldToolsStatus = res.data;
        if (!document.getElementById('scaffold_parent').value && res.data.parent_default) {
            document.getElementById('scaffold_parent').value = res.data.parent_default;
        }
        window.updateScaffoldPrereqUI();
    } catch (e) {
        if (badgesBox) {
            badgesBox.innerHTML = `<span class="text-[11px] text-red-500 font-medium">Gagal memeriksa status tool di komputer.</span>`;
        }
    }
};

window.updateScaffoldPrereqUI = function() {
    const p = window.scaffoldToolsStatus;
    const template = document.getElementById('scaffold_template')?.value || 'laravel';
    const titleEl = document.getElementById('env-fw-title');
    const badgesBox = document.getElementById('scaffold-tool-badges');
    const alertBox = document.getElementById('scaffold-env-alert');
    const submitBtn = document.getElementById('btn-submit-scaffold');

    const fwTitles = {
        laravel: 'Laravel',
        express_prisma: 'Express + Prisma',
        express_drizzle: 'Express + Drizzle',
        springboot_hibernate: 'Spring Boot',
        raw_sql: 'Universal SQL',
    };
    if (titleEl) titleEl.textContent = fwTitles[template] || template;

    if (!p || !badgesBox) return;

    // Tentukan tool yang dibutuhkan sesuai framework yang dipilih
    let required = [];
    if (template === 'laravel') {
        required = [
            { id: 'php', label: 'PHP' },
            { id: 'composer', label: 'Composer' },
            { id: 'git', label: 'Git' },
        ];
    } else if (template === 'express_prisma' || template === 'express_drizzle') {
        required = [
            { id: 'node', label: 'Node.js' },
            { id: 'npm', label: 'NPM' },
            { id: 'git', label: 'Git' },
        ];
    } else if (template === 'springboot_hibernate') {
        required = [
            { id: 'java', label: 'Java (JDK)' },
            { id: 'zip', label: 'Ekstrak Zip' },
            { id: 'internet', label: 'Internet' },
        ];
    } else {
        required = [];
    }

    let allOk = true;
    const missing = [];
    let badgesHtml = '';

    if (required.length === 0) {
        badgesHtml = `<span class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">✔ Tidak memerlukan toolchain khusus. Siap dibuat.</span>`;
    } else {
        required.forEach(item => {
            const status = p[item.id] || { ok: false, version: null };
            const isOk = Boolean(status.ok);
            if (!isOk) {
                allOk = false;
                missing.push(item.label);
            }

            let verLabel = '';
            if (status.version) {
                const match = status.version.match(/(\d+\.\d+(?:\.\d+)?)/);
                if (match) {
                    verLabel = 'v' + match[1];
                } else {
                    const parts = status.version.trim().split(/\s+/);
                    verLabel = parts.slice(0, 2).join(' ');
                }
            }

            badgesHtml += `
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-medium transition-all ${
                    isOk
                        ? 'bg-emerald-500/10 dark:bg-emerald-500/15 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400'
                        : 'bg-red-500/10 dark:bg-red-500/15 border border-red-500/30 text-red-600 dark:text-red-400'
                }">
                    <span class="font-bold">${isOk ? '✔' : '✘'}</span>
                    <span>${item.label}</span>
                    ${verLabel ? `<span class="opacity-70 font-mono text-[10px]">(${verLabel})</span>` : (isOk ? '' : `<span class="opacity-80 text-[10px]">(Belum ada)</span>`)}
                </span>
            `;
        });
    }

    badgesBox.innerHTML = badgesHtml;

    // Tampilkan peringatan jika ada tool yang belum tersedia & atur disable tombol
    if (alertBox) {
        if (!allOk && missing.length > 0) {
            alertBox.className = 'text-[11.5px] p-2.5 rounded-lg border bg-amber-500/10 border-amber-500/20 text-amber-600 dark:text-amber-400 flex items-start gap-2';
            alertBox.innerHTML = `
                <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <div>
                    <strong>Tool belum lengkap:</strong> ${missing.join(', ')} belum terdeteksi. Silakan pasang tool tersebut di komputer atau klik tombol <em>Periksa Ulang</em> jika baru menginstalnya.
                </div>
            `;
            alertBox.classList.remove('hidden');
        } else {
            alertBox.classList.add('hidden');
        }
    }

    if (submitBtn) {
        submitBtn.disabled = !allOk;
        if (!allOk) {
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            submitBtn.title = `Tool belum lengkap: ${missing.join(', ')}`;
        } else {
            submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            submitBtn.title = 'Buat Proyek';
        }
    }
};

window.pickScaffoldParent = async function() {
    try {
        if (typeof window.desktopPickFolder === 'function') {
            const tauriPick = await window.desktopPickFolder();
            if (tauriPick && !tauriPick.cancelled && tauriPick.path) {
                document.getElementById('scaffold_parent').value = tauriPick.path;
                return;
            }
        }
        const result = await window.api('/api/projects/browse', { method: 'POST' });
        if (!result.cancelled && result.path) {
            document.getElementById('scaffold_parent').value = result.path;
        }
    } catch (e) {}
};

window.submitScaffold = async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-submit-scaffold');
    btn.disabled = true;
    document.getElementById('scaffold-progress').classList.remove('hidden');
    document.getElementById('scaffold-log').textContent = '';
    const banner = document.getElementById('scaffold-success-banner');
    if (banner) banner.classList.add('hidden');
    const cursorLine = document.getElementById('scaffold-cursor-line');
    if (cursorLine) {
        cursorLine.innerHTML = `
            <span class="text-zinc-500">➜</span>
            <span class="text-emerald-400 font-semibold">devarchitect</span>
            <span class="text-zinc-500">git:(main)</span>
            <span class="inline-block w-1.5 h-3.5 bg-emerald-400 animate-pulse ml-0.5"></span>
        `;
    }

    const statusEl = document.getElementById('scaffold-status');
    if (statusEl) {
        statusEl.textContent = 'QUEUED';
        statusEl.className = 'uppercase font-semibold tracking-wider text-amber-400';
    }

    try {
        const res = await window.api('/api/projects/scaffold', {
            method: 'POST',
            body: {
                template: document.getElementById('scaffold_template').value,
                project_name: document.getElementById('scaffold_name').value.trim(),
                parent_path: document.getElementById('scaffold_parent').value.trim(),
                spring_group: document.getElementById('spring_group').value.trim() || undefined,
                spring_artifact: document.getElementById('spring_artifact').value.trim() || undefined,
            },
        });
        scaffoldJobId = res.data.id;
        if (res.external_console) {
            document.getElementById('scaffold-external-hint')?.classList.remove('hidden');
            window.toast('Jendela PowerShell dibuka di desktop...', 'info');
        } else {
            window.toast('Memulai proses instalasi...', 'info');
        }
        window.pollScaffold();
        scaffoldTimer = setInterval(window.pollScaffold, 500);
    } catch (e) {
        btn.disabled = false;
    }
};

window.pollScaffold = async function() {
    if (!scaffoldJobId) return;
    try {
        const res = await window.api(`/api/scaffold/${scaffoldJobId}/status`);
        const d = res.data;
        const statusEl = document.getElementById('scaffold-status');
        if (statusEl) {
            if (d.status === 'processing') {
                statusEl.textContent = 'POWERSHELL ACTIVE';
                statusEl.className = 'uppercase font-semibold tracking-wider text-amber-400';
            } else if (d.status === 'ready') {
                statusEl.textContent = 'SELESAI';
                statusEl.className = 'uppercase font-semibold tracking-wider text-emerald-400';
            } else if (d.status === 'failed' || d.status === 'cancelled') {
                statusEl.textContent = (d.status || '').toUpperCase();
                statusEl.className = 'uppercase font-semibold tracking-wider text-red-400';
            } else {
                statusEl.textContent = (d.status || '').toUpperCase();
            }
        }

        const logEl = document.getElementById('scaffold-log');
        if (logEl) {
            logEl.textContent = d.log || '';
        }

        const termBody = document.getElementById('scaffold-terminal-body');
        if (termBody) {
            termBody.scrollTop = termBody.scrollHeight;
        }

        if (['ready', 'failed', 'cancelled'].includes(d.status)) {
            clearInterval(scaffoldTimer);
            scaffoldTimer = null;
            document.getElementById('btn-submit-scaffold').disabled = false;
            const cursorLine = document.getElementById('scaffold-cursor-line');
            
            if (d.status === 'ready') {
                if (cursorLine) {
                    cursorLine.innerHTML = '<span class="text-emerald-400 font-semibold">✔ Proses instalasi selesai dengan sukses.</span>';
                }
                const banner = document.getElementById('scaffold-success-banner');
                if (banner) {
                    banner.classList.remove('hidden');
                }
                window.toast('Proyek berhasil dibuat & terdaftar!', 'success');
                let countdown = 4;
                const msgEl = document.getElementById('scaffold-success-msg');
                const cdInterval = setInterval(() => {
                    countdown--;
                    if (msgEl) {
                        msgEl.textContent = `✔ Proyek berhasil dibuat! Memuat dashboard dalam ${countdown} detik...`;
                    }
                    if (countdown <= 0) {
                        clearInterval(cdInterval);
                        window.location.reload();
                    }
                }, 1000);
            } else {
                if (cursorLine) {
                    cursorLine.innerHTML = '<span class="text-red-400 font-semibold">✘ Proses instalasi terhenti / gagal.</span>';
                }
                window.toast(d.error || 'Pembuatan proyek gagal.', 'error');
            }
        }
    } catch (e) {}
};

window.cancelScaffold = async function() {
    if (!scaffoldJobId) return;
    try {
        await window.api(`/api/scaffold/${scaffoldJobId}/cancel`, { method: 'POST' });
    } catch (e) {}
};

window.submitAddProject = async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-submit-add');
    btn.disabled = true;
    btn.textContent = 'Menyimpan...';

    const payload = {
        project_name: document.getElementById('modal_project_name').value.trim(),
        absolute_path: document.getElementById('modal_absolute_path').value.trim(),
        framework_type: document.getElementById('modal_framework_type').value,
        database_dialect: document.getElementById('modal_database_dialect')?.value || null,
    };

    try {
        const res = await window.api('/api/projects', {
            method: 'POST',
            body: payload
        });

        window.toast(res.message || 'Proyek berhasil didaftarkan.', 'success');
        window.closeAddModal();
        setTimeout(() => window.location.reload(), 500);
    } catch (err) {
        btn.disabled = false;
        btn.textContent = 'Simpan Proyek';
    }
};

window.setActiveProject = async function(id) {
    try {
        const res = await window.api('/api/projects/active', {
            method: 'POST',
            body: { project_id: id }
        });

        window.toast(res.message || 'Project aktif diperbarui.', 'success');
        setTimeout(() => window.location.reload(), 400);
    } catch (err) { }
};

window.openProjectAndGo = async function(id) {
    try {
        const res = await window.api('/api/projects/active', {
            method: 'POST',
            body: { project_id: id }
        });
        window.toast(res.message || 'Membuka project...', 'success');
        setTimeout(() => {
            window.location.href = '/generator';
        }, 300);
    } catch (err) { }
};

window.openEditor = async function(projectId, target) {
    try {
        const res = await window.api(`/api/projects/${projectId}/open`, {
            method: 'POST',
            body: { target }
        });
        window.toast(res.message || `Membuka di ${target}...`, 'success');
    } catch (err) { }
};

window.confirmDeleteProject = function(id, name) {
    pendingDeleteId = id;
    document.getElementById('delete-project-name').textContent = name;
    document.getElementById('delete-project-modal').classList.remove('hidden');
};

window.closeDeleteModal = function() {
    pendingDeleteId = null;
    document.getElementById('delete-project-modal').classList.add('hidden');
};

window.executeDeleteProject = async function() {
    if (!pendingDeleteId) return;
    const btn = document.getElementById('btn-confirm-delete');
    btn.disabled = true;

    try {
        const res = await window.api(`/api/projects/${pendingDeleteId}`, { method: 'DELETE' });
        window.toast(res.message || 'Proyek berhasil dihapus.', 'success');
        window.closeDeleteModal();
        setTimeout(() => window.location.reload(), 400);
    } catch (err) {
        btn.disabled = false;
    }
};

window.copyPathWithFeedback = function(path, btnEl, event) {
    if (event && event.stopPropagation) {
        event.stopPropagation();
    }
    if (!path) return;

    navigator.clipboard.writeText(path).then(() => {
        if (btnEl) {
            const iconEl = btnEl.querySelector('.path-icon');
            const textEl = btnEl.querySelector('.path-text');
            const origIconSvg = iconEl ? iconEl.innerHTML : '';
            const origText = textEl ? textEl.textContent : '';

            if (iconEl) {
                iconEl.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>';
                iconEl.classList.remove('text-zinc-400');
                iconEl.classList.add('text-emerald-500');
            }
            if (textEl) {
                textEl.textContent = 'Tersalin!';
                textEl.classList.add('text-emerald-600', 'dark:text-emerald-400');
            }

            setTimeout(() => {
                if (iconEl) {
                    iconEl.innerHTML = origIconSvg;
                    iconEl.classList.remove('text-emerald-500');
                    iconEl.classList.add('text-zinc-400');
                }
                if (textEl) {
                    textEl.textContent = origText;
                    textEl.classList.remove('text-emerald-600', 'dark:text-emerald-400');
                }
            }, 1600);
        }
        if (window.toast) {
            window.toast('Path direktori berhasil disalin ke clipboard.', 'info');
        }
    }).catch(() => {
        if (window.toast) {
            window.toast('Gagal menyalin path ke clipboard.', 'error');
        }
    });
};

window.copyToClipboard = function(text) {
    navigator.clipboard.writeText(text).then(() => {
        if (window.toast) {
            window.toast('Path direktori disalin ke clipboard.', 'info');
        }
    });
};
