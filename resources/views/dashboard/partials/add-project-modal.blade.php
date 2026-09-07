<!-- Modal: Konfirmasi Tambah Proyek Baru (Radix Monochrome) -->
<div id="add-project-modal"
    class="hidden fixed inset-0 z-50 bg-black/60 dark:bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-[#0c0c0e] border border-zinc-200 dark:border-zinc-800 rounded-xl max-w-xl w-full p-5 shadow-2xl space-y-4 transition-colors">
        <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-800 pb-3">
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Tambah Proyek</h3>
            <button type="button" onclick="closeAddModal()"
                class="text-zinc-400 hover:text-zinc-900 dark:hover:text-white text-lg leading-none">&times;</button>
        </div>

        <div class="grid grid-cols-2 gap-1 p-1 bg-zinc-100 dark:bg-zinc-900 rounded-lg text-xs font-semibold">
            <button type="button" id="tab-btn-existing" onclick="switchAddTab('existing')"
                class="py-1.5 rounded-md bg-white dark:bg-zinc-950 text-zinc-900 dark:text-white shadow-xs">Buka Folder yang Ada</button>
            <button type="button" id="tab-btn-scaffold" onclick="switchAddTab('scaffold')"
                class="py-1.5 rounded-md text-zinc-500 dark:text-zinc-400">Buat Proyek Baru dari Nol</button>
        </div>

        <div id="tab-existing">

        <button type="button" onclick="pickExistingFolder()" id="btn-pick-existing"
            class="w-full py-2.5 mb-1 rounded-lg border border-dashed border-zinc-300 dark:border-zinc-700 text-xs font-medium text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:border-zinc-500 dark:border-zinc-500 transition-colors flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
            Pilih Folder dari Komputer...
        </button>
        <form id="add-project-form" onsubmit="submitAddProject(event)" class="space-y-3.5">
            <div>
                <label
                    class="block text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider mb-1.5">Nama Proyek</label>
                <input type="text" id="modal_project_name" name="project_name" required
                    class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 focus:border-zinc-900 dark:focus:border-zinc-400 focus:ring-1 focus:ring-zinc-900 dark:focus:ring-zinc-400 rounded-lg px-3 py-2 text-xs text-zinc-900 dark:text-white transition-colors">
            </div>

            <div>
                <label
                    class="block text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider mb-1.5">Lokasi Folder Proyek</label>
                <input type="text" id="modal_absolute_path" name="absolute_path" readonly
                    class="w-full bg-zinc-100 dark:bg-zinc-950/60 border border-zinc-200 dark:border-zinc-800 rounded-lg px-3 py-2 text-xs text-zinc-600 dark:text-zinc-400 font-mono select-all">
            </div>

            <div>
                <label
                    class="block text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider mb-1.5">Jenis Framework Aplikasi</label>
                <select id="modal_framework_type" name="framework_type"
                    class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 focus:border-zinc-900 dark:focus:border-zinc-400 focus:ring-1 focus:ring-zinc-900 dark:focus:ring-zinc-400 rounded-lg px-3 py-2 text-xs text-zinc-900 dark:text-white transition-colors">
                    <option value="laravel">Laravel (Eloquent Migrations)</option>
                    <option value="express_prisma">Express.js (Prisma Schema)</option>
                    <option value="express_drizzle">Express.js (Drizzle ORM)</option>
                    <option value="springboot_hibernate">Spring Boot (JPA / Hibernate)</option>
                    <option value="raw_sql">Universal Raw SQL</option>
                </select>
                <p id="modal_detection_msg" class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-1.5"></p>
            </div>

            <div>
                <label
                    class="block text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider mb-1.5">Dialek Database Sasaran</label>
                <select id="modal_database_dialect" name="database_dialect"
                    class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 focus:border-zinc-900 dark:focus:border-zinc-400 focus:ring-1 focus:ring-zinc-900 dark:focus:ring-zinc-400 rounded-lg px-3 py-2 text-xs text-zinc-900 dark:text-white transition-colors">
                    <option value="mysql">MySQL / MariaDB</option>
                    <option value="pgsql">PostgreSQL</option>
                    <option value="sqlite">SQLite</option>
                    <option value="sqlsrv">Microsoft SQL Server</option>
                </select>
                <p id="modal_dialect_msg" class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-1.5"></p>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                <button type="button" onclick="closeAddModal()"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                    Batal
                </button>
                <button type="submit" id="btn-submit-add"
                    class="px-4 py-1.5 bg-black text-white hover:bg-zinc-800 dark:bg-white dark:text-black dark:hover:bg-zinc-200 font-semibold rounded-lg text-xs transition-colors shadow-xs">
                    Simpan Proyek
                </button>
            </div>
        </form>
        </div><!-- /tab-existing -->

        <div id="tab-scaffold" class="hidden space-y-3.5">
            <form id="scaffold-form" onsubmit="submitScaffold(event)" class="space-y-3.5">
                <!-- Pilihan Framework Berbasis Kartu Interaktif (Minimalist) -->
                <div>
                    <label class="block text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider mb-2">Pilihan Framework Proyek</label>
                    <input type="hidden" id="scaffold_template" name="scaffold_template" value="laravel">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5" id="scaffold-framework-cards">
                        <!-- Laravel -->
                        <button type="button" onclick="selectScaffoldTemplate('laravel')" data-template="laravel"
                            class="scaffold-fw-card p-2.5 rounded-xl border border-zinc-900 dark:border-white bg-zinc-100/70 dark:bg-zinc-900/60 transition-all flex flex-col justify-between text-left cursor-pointer group relative shadow-xs">
                            <div class="flex items-start justify-between w-full mb-2">
                                <div class="w-8 h-8 rounded-lg bg-red-500/10 dark:bg-red-500/20 flex items-center justify-center p-1.5 shrink-0">
                                    <img src="{{ asset('assets/icons/Laravel.svg') }}" class="w-full h-full object-contain" alt="Laravel">
                                </div>
                                <span class="w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0 text-[10px] font-bold fw-check transition-opacity">✔</span>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-zinc-900 dark:text-white leading-snug">Laravel</div>
                                <div class="text-[10px] text-zinc-400 dark:text-zinc-500 mt-0.5">PHP • Composer</div>
                            </div>
                        </button>

                        <!-- Express + Prisma -->
                        <button type="button" onclick="selectScaffoldTemplate('express_prisma')" data-template="express_prisma"
                            class="scaffold-fw-card p-2.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 transition-all flex flex-col justify-between text-left cursor-pointer group relative shadow-xs hover:border-zinc-400 dark:hover:border-zinc-700">
                            <div class="flex items-start justify-between w-full mb-2">
                                <div class="w-8 h-8 rounded-lg bg-zinc-900 dark:bg-zinc-800 flex items-center justify-center p-1.5 shrink-0">
                                    <img src="{{ asset('assets/icons/ExpressJS.svg') }}" class="w-full h-full object-contain" alt="Express.js">
                                </div>
                                <span class="w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0 text-[10px] font-bold fw-check opacity-0 transition-opacity">✔</span>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-zinc-900 dark:text-white leading-snug">Express + Prisma</div>
                                <div class="text-[10px] text-zinc-400 dark:text-zinc-500 mt-0.5">Node.js • Prisma</div>
                            </div>
                        </button>

                        <!-- Express + Drizzle -->
                        <button type="button" onclick="selectScaffoldTemplate('express_drizzle')" data-template="express_drizzle"
                            class="scaffold-fw-card p-2.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 transition-all flex flex-col justify-between text-left cursor-pointer group relative shadow-xs hover:border-zinc-400 dark:hover:border-zinc-700">
                            <div class="flex items-start justify-between w-full mb-2">
                                <div class="w-8 h-8 rounded-lg bg-zinc-900 dark:bg-zinc-800 flex items-center justify-center p-1.5 shrink-0">
                                    <img src="{{ asset('assets/icons/ExpressJS.svg') }}" class="w-full h-full object-contain" alt="Express.js">
                                </div>
                                <span class="w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0 text-[10px] font-bold fw-check opacity-0 transition-opacity">✔</span>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-zinc-900 dark:text-white leading-snug">Express + Drizzle</div>
                                <div class="text-[10px] text-zinc-400 dark:text-zinc-500 mt-0.5">Node.js • Drizzle</div>
                            </div>
                        </button>

                        <!-- Spring Boot -->
                        <button type="button" onclick="selectScaffoldTemplate('springboot_hibernate')" data-template="springboot_hibernate"
                            class="scaffold-fw-card p-2.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 transition-all flex flex-col justify-between text-left cursor-pointer group relative shadow-xs hover:border-zinc-400 dark:hover:border-zinc-700">
                            <div class="flex items-start justify-between w-full mb-2">
                                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 dark:bg-emerald-500/20 flex items-center justify-center p-1.5 shrink-0">
                                    <img src="{{ asset('assets/icons/Springboot.svg') }}" class="w-full h-full object-contain" alt="Spring Boot">
                                </div>
                                <span class="w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0 text-[10px] font-bold fw-check opacity-0 transition-opacity">✔</span>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-zinc-900 dark:text-white leading-snug">Spring Boot</div>
                                <div class="text-[10px] text-zinc-400 dark:text-zinc-500 mt-0.5">Java • Hibernate</div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Panel Kesiapan Environment Komputer Interaktif -->
                <div id="scaffold-env-panel" class="rounded-xl border border-zinc-200 dark:border-zinc-800/90 bg-zinc-50/70 dark:bg-zinc-900/40 p-3 space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5 text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                            <svg class="w-3.5 h-3.5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <span>Kesiapan Tool untuk <span id="env-fw-title" class="text-zinc-900 dark:text-white font-bold">Laravel</span></span>
                        </div>
                        <button type="button" onclick="loadScaffoldPrereq()" title="Periksa Ulang Kesiapan Tool"
                            class="text-[11px] text-zinc-500 hover:text-zinc-900 dark:hover:text-white flex items-center gap-1 px-2 py-0.5 rounded hover:bg-zinc-200 dark:hover:bg-zinc-800 transition-colors">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            <span>Periksa Ulang</span>
                        </button>
                    </div>

                    <!-- Dynamic Tool Badges -->
                    <div id="scaffold-tool-badges" class="flex flex-wrap items-center gap-1.5">
                        <span class="text-[11px] text-zinc-400 animate-pulse">Memeriksa ketersediaan tool di komputer...</span>
                    </div>

                    <!-- Alert Box jika ada tool yang kurang -->
                    <div id="scaffold-env-alert" class="hidden text-[11.5px] p-2.5 rounded-lg border flex items-start gap-2"></div>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider mb-1.5">Nama Folder Proyek (huruf kecil & tanpa spasi)</label>
                    <input type="text" id="scaffold_name" required pattern="[a-z0-9][a-z0-9-_]{1,60}" maxlength="61" placeholder="toko-bekas"
                        class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-lg px-3 py-2 text-xs font-mono text-zinc-900 dark:text-white">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider mb-1.5">Lokasi Penyimpanan Folder</label>
                    <div class="flex gap-2">
                        <input type="text" id="scaffold_parent" placeholder="C:\Path\Ke\Folder\Parent"
                            class="flex-1 min-w-0 bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-lg px-3 py-2 text-xs text-zinc-900 dark:text-white font-mono">
                        <button type="button" onclick="pickScaffoldParent()"
                            class="px-3 py-2 bg-zinc-100 dark:bg-zinc-900 hover:bg-zinc-200 dark:hover:bg-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded-lg text-xs font-medium shrink-0">Pilih Lokasi...</button>
                    </div>
                    <p class="text-[10.5px] text-zinc-500 dark:text-zinc-400 mt-1">Proyek baru Anda akan otomatis dibuatkan folder di lokasi ini.</p>
                </div>
                <div id="spring-options" class="hidden grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-semibold text-zinc-500 uppercase tracking-wider mb-1.5">GroupId</label>
                        <input type="text" id="spring_group" value="com.example" maxlength="120"
                            class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-lg px-3 py-2 text-xs font-mono">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-zinc-500 uppercase tracking-wider mb-1.5">ArtifactId</label>
                        <input type="text" id="spring_artifact" placeholder="= nama proyek" maxlength="61"
                            class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-lg px-3 py-2 text-xs font-mono">
                    </div>
                </div>
                <div id="scaffold-progress" class="hidden space-y-2 pt-2">
                    <!-- Terminal Window Frame (macOS / Linux Dev Aesthetic) -->
                    <div class="rounded-xl overflow-hidden border border-zinc-800 bg-[#09090b] shadow-2xl">
                        <!-- Terminal Top Bar -->
                        <div class="flex items-center justify-between px-3.5 py-2 bg-zinc-900/90 border-b border-zinc-800/80">
                            <div class="flex items-center gap-2">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-red-500/80 inline-block"></span>
                                    <span class="w-2.5 h-2.5 rounded-full bg-yellow-500/80 inline-block"></span>
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500/80 inline-block"></span>
                                </div>
                                <span class="text-[11px] text-zinc-400 font-medium ml-1">Terminal — DEVArchitect Console</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-1.5 text-[10px] font-mono text-zinc-400">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    <span id="scaffold-status" class="uppercase font-semibold tracking-wider text-emerald-400">QUEUED</span>
                                </div>
                                <button type="button" onclick="cancelScaffold()" class="text-[11px] font-medium text-red-400 hover:text-red-300 transition-colors">
                                    Batalkan
                                </button>
                            </div>
                        </div>
                        <!-- External Console Hint Banner -->
                        <div id="scaffold-external-hint" class="hidden flex items-center gap-2 px-3.5 py-2 bg-blue-500/10 border-b border-blue-500/20 text-xs text-blue-400">
                            <span class="animate-pulse">⚡</span>
                            <span>Jendela <strong>PowerShell</strong> terbuka di desktop. Proses instalasi berjalan secara interaktif di konsol.</span>
                        </div>
                        <!-- Terminal Screen Output -->
                        <div class="p-3.5 font-mono text-xs text-zinc-200 h-64 overflow-y-auto space-y-1 select-text scroll-smooth" id="scaffold-terminal-body">
                            <pre id="scaffold-log" class="font-mono text-[11px] leading-relaxed text-zinc-300 whitespace-pre-wrap selection:bg-emerald-500/30"></pre>
                            <div class="flex items-center gap-1 text-emerald-400 text-xs font-mono pt-1" id="scaffold-cursor-line">
                                <span class="text-zinc-500">➜</span>
                                <span class="text-emerald-400 font-semibold">devarchitect</span>
                                <span class="text-zinc-500">git:(main)</span>
                                <span class="inline-block w-1.5 h-3.5 bg-emerald-400 animate-pulse ml-0.5"></span>
                            </div>
                        </div>
                    </div>
                    <!-- Success Countdown Banner -->
                    <div id="scaffold-success-banner" class="hidden flex items-center justify-between p-2.5 bg-emerald-500/10 border border-emerald-500/20 rounded-lg text-xs font-medium text-emerald-400 animate-fade-in">
                        <span id="scaffold-success-msg">✔ Proyek berhasil dibuat! Memuat dashboard dalam 4 detik...</span>
                        <button type="button" onclick="window.location.reload()" class="px-2.5 py-1 bg-emerald-500 text-black font-semibold rounded text-xs hover:bg-emerald-400 transition-colors">
                            Buka Sekarang
                        </button>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                    <button type="button" onclick="closeAddModal()"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white hover:bg-zinc-100 dark:hover:bg-zinc-800">
                        Batal
                    </button>
                    <button type="submit" id="btn-submit-scaffold"
                        class="px-4 py-1.5 bg-black text-white hover:bg-zinc-800 dark:bg-white dark:text-black dark:hover:bg-zinc-200 font-semibold rounded-lg text-xs shadow-xs">
                        Buat Proyek
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
