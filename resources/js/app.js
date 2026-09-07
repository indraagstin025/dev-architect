import { getCurrentWindow } from '@tauri-apps/api/window';
import { LogicalSize, LogicalPosition } from '@tauri-apps/api/dpi';

/**
 * DEVArchitect - Global Frontend Core (Radix UI Minimalist Edition)
 * Vanilla JS, zero-setup, developer-centric helper for desktop app.
 */

// 1. Radix-Style Desktop Toast Notification System (Adaptive Light/Dark)
window.toast = function(message, type = 'success', duration = 4000) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toastEl = document.createElement('div');
    
    // Radix minimal theme icons with Supabase emerald accent
    let iconSvg = '';
    if (type === 'success') {
        iconSvg = `<svg class="w-4 h-4 text-[#3ECF8E] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>`;
    } else if (type === 'error') {
        iconSvg = `<svg class="w-4 h-4 text-red-500 dark:text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>`;
    } else if (type === 'warning') {
        iconSvg = `<svg class="w-4 h-4 text-amber-500 dark:text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>`;
    } else {
        iconSvg = `<svg class="w-4 h-4 text-zinc-500 dark:text-zinc-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
    }

    const borderAccent = type === 'success' ? 'border-l-2 border-l-[#3ECF8E]' : (type === 'error' ? 'border-l-2 border-l-red-500' : '');
    toastEl.className = `flex items-center gap-2.5 px-3.5 py-2.5 rounded-lg border border-zinc-200 dark:border-zinc-800 ${borderAccent} bg-white/95 dark:bg-[#0c0c0e]/95 text-zinc-900 dark:text-zinc-100 shadow-2xl backdrop-blur-md text-xs font-medium transition-all duration-200 transform translate-y-2 opacity-0 select-text`;
    toastEl.innerHTML = `
        ${iconSvg}
        <span class="flex-1 leading-normal" data-toast-msg></span>
        <button type="button" class="text-zinc-400 hover:text-zinc-900 dark:hover:text-white transition-colors ml-1 p-0.5" onclick="this.parentElement.remove()">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    `;

    // Proteksi XSS
    toastEl.querySelector('[data-toast-msg]').textContent = message;

    container.appendChild(toastEl);

    // Animasi masuk
    requestAnimationFrame(() => {
        toastEl.classList.remove('translate-y-2', 'opacity-0');
    });

    // Otomatis hilang setelah durasi
    setTimeout(() => {
        toastEl.classList.add('opacity-0', '-translate-y-1');
        setTimeout(() => toastEl.remove(), 200);
    }, duration);
};

// 2. Helper Global API fetch dengan Auto-Toast & Error Handler
window.api = async function(url, options = {}) {
    const bridgeToken = document.querySelector('meta[name="desktop-bridge-token"]')?.getAttribute('content') || '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const defaultHeaders = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-DEVArchitect-Bridge-Key': bridgeToken,
        'X-CSRF-TOKEN': csrfToken,
    };

    options.headers = { ...defaultHeaders, ...(options.headers || {}) };

    if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
        options.body = JSON.stringify(options.body);
    }

    try {
        const response = await fetch(url, options);
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            let errorMsg = data.message || `Terjadi kesalahan (HTTP ${response.status})`;

            if (data.errors) {
                const firstKey = Object.keys(data.errors)[0];
                if (firstKey && data.errors[firstKey].length > 0) {
                    errorMsg = data.errors[firstKey][0];
                }
            }

            window.toast(errorMsg, 'error');
            throw new Error(errorMsg);
        }

        return data;
    } catch (err) {
        if (!options.silent) {
            console.error('[DEVArchitect API Error]:', err);
        }
        throw err;
    }
};

// 3. Logika Sidebar Hover Rail (Supabase Style: Always 72px Icon Rail)
window.initSidebar = function() {
    const wrapper = document.getElementById('sidebar-wrapper');
    if (wrapper) {
        wrapper.classList.remove('is-pinned');
    }
    localStorage.removeItem('devarchitect_sidebar_pinned');
};

window.togglePinSidebar = function() {};
window.toggleSidebar = function() {};
window.collapseSidebarForCanvas = function() {};

// 4. Dual Theme Management (Dark & Light Mode)
window.initTheme = function() {
    const saved = localStorage.getItem('devarchitect_theme') || 'dark';
    window.setTheme(saved);
};

window.setTheme = function(theme) {
    const html = document.documentElement;
    const isDark = theme === 'dark';
    
    if (isDark) {
        html.classList.add('dark');
        html.classList.remove('light');
    } else {
        html.classList.remove('dark');
        html.classList.add('light');
    }

    localStorage.setItem('devarchitect_theme', isDark ? 'dark' : 'light');

    // Update theme toggle button icons
    const sunIcon = document.getElementById('theme-icon-sun');
    const moonIcon = document.getElementById('theme-icon-moon');
    if (sunIcon && moonIcon) {
        sunIcon.classList.toggle('hidden', isDark);
        moonIcon.classList.toggle('hidden', !isDark);
    }

    window.dispatchEvent(new CustomEvent('theme-changed', { detail: { theme: isDark ? 'dark' : 'light' } }));
};

window.toggleTheme = function() {
    const isDark = document.documentElement.classList.contains('dark');
    window.setTheme(isDark ? 'light' : 'dark');
};

// Helper deteksi runtime Tauri (v2)
function getTauriWindow() {
    try {
        if (typeof window !== 'undefined') {
            if (window.__TAURI_INTERNALS__ || (window.__TAURI__ && window.__TAURI__.window)) {
                return getCurrentWindow();
            }
        }
    } catch (e) {}
    return null;
}

function createLogicalSize(width, height) {
    try {
        if (typeof LogicalSize !== 'undefined') {
            return new LogicalSize(width, height);
        }
    } catch (e) {}
    if (window.__TAURI__?.dpi?.LogicalSize) {
        return new window.__TAURI__.dpi.LogicalSize(width, height);
    }
    return { Logical: { width, height } };
}

function createLogicalPosition(x, y) {
    try {
        if (typeof LogicalPosition !== 'undefined') {
            return new LogicalPosition(x, y);
        }
    } catch (e) {}
    if (window.__TAURI__?.dpi?.LogicalPosition) {
        return new window.__TAURI__.dpi.LogicalPosition(x, y);
    }
    return { Logical: { x, y } };
}

window.desktopPickFolder = async function(defaultPath = null) {
    try {
        if (window.__TAURI__ && window.__TAURI__.dialog && typeof window.__TAURI__.dialog.open === 'function') {
            const selected = await window.__TAURI__.dialog.open({
                directory: true,
                multiple: false,
                title: 'Pilih Direktori Proyek Backend',
                defaultPath: defaultPath || undefined
            });
            if (!selected) {
                return { cancelled: true, path: null };
            }
            return { cancelled: false, path: selected };
        }
    } catch (e) {
        console.warn('Tauri dialog error:', e);
    }
    return null;
};

// 5. Kontrol Jendela Desktop & Bilah Judul Kustom (Tauri Native + Electron + HTTP Fallback)
window.desktopMinimizeWindow = async function() {
    const tauriWin = getTauriWindow();
    if (tauriWin) {
        try {
            await tauriWin.minimize();
            return;
        } catch (e) {}
    }

    if (window.Native && typeof window.Native.minimize === 'function') {
        window.Native.minimize();
        return;
    }
    try {
        await fetch('/api/window/minimize', { 
            method: 'POST', 
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' 
            },
            body: JSON.stringify({ id: 'main' })
        });
    } catch (e) {
        console.warn('Minimize call failed:', e);
    }
};

window.desktopMaximizeWindow = async function() {
    const snapMenu = document.getElementById('win-snap-menu');
    if (snapMenu) snapMenu.classList.add('hidden');

    // Close any active titlebar dropdowns so they don't get trapped during resize
    const titlebar = document.getElementById('desktop-titlebar');
    if (titlebar) {
        titlebar.querySelectorAll('.titlebar-menu-dropdown').forEach(d => d.classList.add('hidden'));
        titlebar.querySelectorAll('.titlebar-menu-trigger').forEach(t => t.classList.remove('bg-zinc-200', 'dark:bg-zinc-800', 'text-zinc-900', 'dark:text-white'));
    }

    const tauriWin = getTauriWindow();
    if (tauriWin) {
        try {
            await tauriWin.toggleMaximize();
            const isMax = await tauriWin.isMaximized();
            window.updateWindowMaximizedUI(isMax);
            return;
        } catch (e) {}
    }

    if (window.Native && typeof window.Native.maximize === 'function') {
        const isMax = window.Native.maximize();
        window.updateWindowMaximizedUI(isMax);
        return;
    }

    try {
        const res = await fetch('/api/window/maximize', { 
            method: 'POST', 
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' 
            },
            body: JSON.stringify({ id: 'main' })
        });
        const data = await res.json();
        if (data && typeof data.isMaximized !== 'undefined') {
            window.updateWindowMaximizedUI(data.isMaximized);
        } else {
            setTimeout(window.refreshWindowStatus, 250);
        }
    } catch (e) {
        console.warn('Maximize call failed:', e);
    }
};

window.desktopSnapWindow = async function(mode) {
    const snapMenu = document.getElementById('win-snap-menu');
    if (snapMenu) snapMenu.classList.add('hidden');

    const tauriWin = getTauriWindow();
    if (tauriWin) {
        try {
            if (mode === 'compact') {
                const wasMax = await tauriWin.isMaximized();
                if (wasMax) {
                    await tauriWin.unmaximize();
                }
                await tauriWin.setSize(createLogicalSize(1080, 720));
                await tauriWin.center();
                window.updateWindowMaximizedUI(false);
                if (window.toast) window.toast('Skala Kompak (Kecil) Diaktifkan', 'info', 2000);
                return;
            } else if (mode === 'fullscreen') {
                await tauriWin.maximize();
                window.updateWindowMaximizedUI(true);
                if (window.toast) window.toast('Mode Layar Penuh Diaktifkan', 'info', 2000);
                return;
            } else if (mode === 'left' || mode === 'right') {
                const wasMax = await tauriWin.isMaximized();
                if (wasMax) {
                    await tauriWin.unmaximize();
                }

                let availW = window.screen.availWidth || 1920;
                let availH = window.screen.availHeight || 1080;
                let availLeft = window.screen.availLeft || 0;
                let availTop = window.screen.availTop || 0;

                try {
                    const monitor = await tauriWin.currentMonitor();
                    if (monitor && monitor.size && monitor.scaleFactor) {
                        availW = Math.round(monitor.size.width / monitor.scaleFactor);
                        availH = Math.round(monitor.size.height / monitor.scaleFactor);
                        if (monitor.position) {
                            availLeft = Math.round(monitor.position.x / monitor.scaleFactor);
                            availTop = Math.round(monitor.position.y / monitor.scaleFactor);
                        }
                    }
                } catch (e) {}

                const halfW = Math.floor(availW / 2);
                const posX = mode === 'left' ? availLeft : (availLeft + halfW);

                await tauriWin.setSize(createLogicalSize(halfW, availH));
                await tauriWin.setPosition(createLogicalPosition(posX, availTop));
                window.updateWindowMaximizedUI(false);
                if (window.toast) {
                    window.toast(
                        mode === 'left' ? 'Mode Multitasking: Sisi Kiri 50%' : 'Mode Multitasking: Sisi Kanan 50%',
                        'info',
                        2000
                    );
                }
                return;
            }
        } catch (e) {
            console.warn('Tauri snap failed:', e);
        }
    }

    if (window.Native && typeof window.Native.snap === 'function') {
        window.Native.snap(mode);
        const isMax = mode === 'fullscreen';
        window.updateWindowMaximizedUI(isMax);
        if (mode === 'compact') {
            if (window.toast) window.toast('Skala Kompak (Kecil) Diaktifkan', 'info', 2000);
        } else if (mode === 'fullscreen') {
            if (window.toast) window.toast('Mode Layar Penuh Diaktifkan', 'info', 2000);
        } else if (mode === 'left') {
            if (window.toast) window.toast('Mode Multitasking: Sisi Kiri 50%', 'info', 2000);
        } else if (mode === 'right') {
            if (window.toast) window.toast('Mode Multitasking: Sisi Kanan 50%', 'info', 2000);
        }
        return;
    }

    try {
        await fetch('/api/window/snap', { 
            method: 'POST', 
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' 
            },
            body: JSON.stringify({ id: 'main', mode })
        });

        if (mode === 'compact') {
            window.updateWindowMaximizedUI(false);
            if (window.toast) window.toast('Skala Kompak (Kecil) Diaktifkan', 'info', 2000);
        } else if (mode === 'fullscreen') {
            window.updateWindowMaximizedUI(true);
            if (window.toast) window.toast('Mode Layar Penuh Diaktifkan', 'info', 2000);
        } else if (mode === 'left') {
            window.updateWindowMaximizedUI(false);
            if (window.toast) window.toast('Mode Multitasking: Sisi Kiri 50%', 'info', 2000);
        } else if (mode === 'right') {
            window.updateWindowMaximizedUI(false);
            if (window.toast) window.toast('Mode Multitasking: Sisi Kanan 50%', 'info', 2000);
        }
    } catch (e) {
        console.warn('Snap call failed:', e);
    }
};

window.updateWindowMaximizedUI = function(isMaximized) {
    try {
        localStorage.setItem('devarchitect_is_maximized', isMaximized ? 'true' : 'false');
    } catch (e) {}

    const maxIcon = document.getElementById('win-max-icon');
    const restoreIcon = document.getElementById('win-restore-icon');
    const maxBtn = document.getElementById('win-max-btn');
    if (maxIcon && restoreIcon) {
        maxIcon.classList.toggle('hidden', !!isMaximized);
        restoreIcon.classList.toggle('hidden', !isMaximized);
    }
    if (maxBtn) {
        maxBtn.title = isMaximized 
            ? 'Kembalikan ke Ukuran Lebih Kecil (Arahkan mouse untuk Mode Multitasking)' 
            : 'Maksimalkan Layar (Arahkan mouse untuk Mode Multitasking)';
    }
    document.body.classList.toggle('is-window-maximized', !!isMaximized);
    document.documentElement.classList.toggle('is-window-maximized', !!isMaximized);
};

window.refreshWindowStatus = async function() {
    const tauriWin = getTauriWindow();
    if (tauriWin) {
        try {
            const isMax = await tauriWin.isMaximized();
            window.updateWindowMaximizedUI(isMax);
            return;
        } catch (e) {}
    }

    if (window.Native && typeof window.Native.isMaximized === 'function') {
        const isMax = window.Native.isMaximized();
        window.updateWindowMaximizedUI(isMax);
        return;
    }
    try {
        const res = await fetch('/api/window/status?id=main');
        const data = await res.json();
        if (data && typeof data.isMaximized !== 'undefined') {
            window.updateWindowMaximizedUI(data.isMaximized);
        }
    } catch (e) {}
};

window.desktopCloseWindow = async function() {
    const tauriWin = getTauriWindow();
    if (tauriWin) {
        try {
            await tauriWin.close();
            return;
        } catch (e) {}
    }

    if (window.Native && typeof window.Native.close === 'function') {
        window.Native.close();
        return;
    }
    try {
        await fetch('/api/window/close', { 
            method: 'POST', 
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' 
            },
            body: JSON.stringify({ id: 'main' })
        });
    } catch (e) {
        window.close();
    }
};

window.desktopToggleFullscreen = function() {
    // In Electron frameless apps, HTML5 requestFullscreen() corrupts the -webkit-app-region drag mapping.
    // Instead, toggle native window maximization/restoration cleanly.
    if (document.fullscreenElement) {
        document.exitFullscreen().catch(() => {});
    }
    window.desktopMaximizeWindow();
};

document.addEventListener('fullscreenchange', () => {
    const titlebar = document.getElementById('desktop-titlebar');
    if (titlebar) {
        titlebar.style.display = 'none';
        void titlebar.offsetHeight;
        titlebar.style.display = '';
    }
});

window.desktopPaste = async function() {
    try {
        const text = await navigator.clipboard.readText();
        const active = document.activeElement;
        if (active && (active.tagName === 'TEXTAREA' || active.tagName === 'INPUT')) {
            const start = active.selectionStart;
            const end = active.selectionEnd;
            active.value = active.value.substring(0, start) + text + active.value.substring(end);
            active.selectionStart = active.selectionEnd = start + text.length;
            active.dispatchEvent(new Event('input', { bubbles: true }));
        }
    } catch (e) {
        document.execCommand('paste');
    }
};

// 6. Skala Zoom Interaktif
window.currentAppZoom = parseFloat(localStorage.getItem('devarchitect_app_zoom') || '0.90');
window.zoomApp = function(delta) {
    if (delta === 0) {
        window.currentAppZoom = 0.90;
    } else {
        window.currentAppZoom = Math.max(0.70, Math.min(1.40, window.currentAppZoom + delta));
    }
    document.documentElement.style.zoom = window.currentAppZoom.toFixed(2);
    localStorage.setItem('devarchitect_app_zoom', window.currentAppZoom.toFixed(2));
    if (window.toast) {
        window.toast(`Skala Tampilan: ${Math.round(window.currentAppZoom * 100)}%`, 'info', 1500);
    }
};

// 7. Inisialisasi Desktop Titlebar & Dropdown Menus
window.initDesktopTitlebar = function() {
    const titlebar = document.getElementById('desktop-titlebar');
    if (!titlebar) return;

    // Terapkan zoom yang tersimpan jika ada
    if (localStorage.getItem('devarchitect_app_zoom')) {
        const savedZoom = parseFloat(localStorage.getItem('devarchitect_app_zoom'));
        if (!isNaN(savedZoom)) {
            window.currentAppZoom = savedZoom;
            document.documentElement.style.zoom = savedZoom.toFixed(2);
        }
    }

    const menuItems = titlebar.querySelectorAll('.titlebar-menu-item');
    let isAnyMenuOpen = false;

    function closeAllMenus() {
        menuItems.forEach(item => {
            const dropdown = item.querySelector('.titlebar-menu-dropdown');
            const trigger = item.querySelector('.titlebar-menu-trigger');
            if (dropdown) dropdown.classList.add('hidden');
            if (trigger) trigger.classList.remove('bg-zinc-200', 'dark:bg-zinc-800', 'text-zinc-900', 'dark:text-white');
        });
        isAnyMenuOpen = false;
    }

    function openMenu(item) {
        closeAllMenus();
        const dropdown = item.querySelector('.titlebar-menu-dropdown');
        const trigger = item.querySelector('.titlebar-menu-trigger');
        if (dropdown) {
            dropdown.classList.remove('hidden');
            if (trigger) trigger.classList.add('bg-zinc-200', 'dark:bg-zinc-800', 'text-zinc-900', 'dark:text-white');
            isAnyMenuOpen = true;
        }
    }

    menuItems.forEach(item => {
        const trigger = item.querySelector('.titlebar-menu-trigger');
        const dropdown = item.querySelector('.titlebar-menu-dropdown');
        if (!trigger) return;

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            const isCurrentlyOpen = dropdown && !dropdown.classList.contains('hidden');
            if (isCurrentlyOpen) {
                closeAllMenus();
            } else {
                openMenu(item);
            }
        });

        // Stop clicks inside dropdown from bubbling to titlebar / document
        if (dropdown) {
            dropdown.addEventListener('click', (e) => {
                e.stopPropagation();
                // Close menu after action (small delay so onclick handler fires first)
                setTimeout(() => closeAllMenus(), 80);
            });
        }

        item.addEventListener('mouseenter', () => {
            if (isAnyMenuOpen) {
                openMenu(item);
            }
        });
    });

    document.addEventListener('click', (e) => {
        if (!titlebar.contains(e.target)) {
            closeAllMenus();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeAllMenus();
        }

        if (e.key === 'F11') {
            e.preventDefault();
            window.desktopToggleFullscreen();
        }

        // Global Desktop Shortcuts (Ctrl+1, Ctrl+2, Ctrl+3, Ctrl+4, Ctrl+, Zoom)
        if ((e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey) {
            if (e.key === '1') {
                e.preventDefault();
                window.location.href = '/assistant';
            } else if (e.key === '2') {
                e.preventDefault();
                window.location.href = '/dashboard';
            } else if (e.key === '3') {
                e.preventDefault();
                window.location.href = '/generator';
            } else if (e.key === '4') {
                e.preventDefault();
                window.location.href = '/history';
            } else if (e.key === ',') {
                e.preventDefault();
                window.location.href = '/settings';
            } else if (e.key === '=' || e.key === '+') {
                e.preventDefault();
                window.zoomApp(0.05);
            } else if (e.key === '-') {
                e.preventDefault();
                window.zoomApp(-0.05);
            } else if (e.key === '0') {
                e.preventDefault();
                window.zoomApp(0);
            }
        }
    });

    // Snap & Multitasking Layout Popup on Maximize Button (Windows 11 Snap Assist style)
    const snapContainer = document.getElementById('win-snap-container');
    const snapMenu = document.getElementById('win-snap-menu');
    let snapOpenTimer = null;
    let snapHideTimer = null;

    if (snapContainer && snapMenu) {
        const openMenu = () => {
            clearTimeout(snapHideTimer);
            snapOpenTimer = setTimeout(() => {
                snapMenu.classList.remove('hidden');
            }, 160);
        };

        const scheduleClose = () => {
            clearTimeout(snapOpenTimer);
            snapHideTimer = setTimeout(() => {
                snapMenu.classList.add('hidden');
            }, 260);
        };

        snapContainer.addEventListener('mouseenter', openMenu);
        snapContainer.addEventListener('mouseleave', scheduleClose);
        snapMenu.addEventListener('mouseenter', () => clearTimeout(snapHideTimer));
        snapMenu.addEventListener('mouseleave', scheduleClose);

        const maxBtn = document.getElementById('win-max-btn');
        if (maxBtn) {
            maxBtn.addEventListener('contextmenu', (e) => {
                e.preventDefault();
                clearTimeout(snapOpenTimer);
                clearTimeout(snapHideTimer);
                snapMenu.classList.toggle('hidden');
            });
        }
    }

    // Double-click titlebar to toggle maximize / restore like native desktop apps
    // Guards: must not be inside any interactive/no-drag/dropdown element, debounced against OS native double-click
    let lastDblClickTime = 0;
    titlebar.addEventListener('dblclick', (e) => {
        const isInteractive = e.target.closest('.app-no-drag, button, a, input, [role="button"], .titlebar-menu-dropdown, #win-snap-menu');
        const isAnyDropdownOpen = titlebar.querySelector('.titlebar-menu-dropdown:not(.hidden)') !== null;
        const now = Date.now();
        if (!isInteractive && !isAnyDropdownOpen && (now - lastDblClickTime > 500)) {
            lastDblClickTime = now;
            window.desktopMaximizeWindow();
        }
    });

    // Native Window State Listener from Preload
    window.addEventListener('message', (e) => {
        if (e.data && e.data.type === 'window-maximized-state') {
            window.updateWindowMaximizedUI(!!e.data.isMaximized);
        }
    });

    // Automatically sync window state on window resize (e.g. Windows Aero Snap, Win+Up, Win+Down)
    let resizeSyncTimer = null;
    window.addEventListener('resize', () => {
        clearTimeout(resizeSyncTimer);
        resizeSyncTimer = setTimeout(() => {
            if (typeof window.refreshWindowStatus === 'function') {
                window.refreshWindowStatus();
            }
        }, 120);
    });

    // Refresh initial window state
    if (typeof window.refreshWindowStatus === 'function') {
        window.refreshWindowStatus();
    }
};

document.addEventListener('DOMContentLoaded', () => {
    window.initSidebar();
    window.initTheme();
    window.initDesktopTitlebar();
});

