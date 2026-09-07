@extends('layouts.app')

@php
    $headerTitle = 'Pengaturan';
    $headerSubtitle = 'Konfigurasi Model AI & Preferensi Sistem';

    $apiKey = \App\Models\AppSetting::get('openrouter_api_key', config('services.openrouter.key', ''));
    $hasApiKey = !empty($apiKey);
    $maskedKey = $hasApiKey ? substr($apiKey, 0, 8) . '••••' . substr($apiKey, -4) : '';
    $currentModel = \App\Models\AppSetting::get('openrouter_model', config('services.openrouter.model', 'openai/gpt-oss-120b'));
    $baseUrl = \App\Models\AppSetting::get('openrouter_base_url', config('services.openrouter.base_url', 'https://openrouter.ai/api/v1'));
    $docsModel = \App\Models\AppSetting::get('openrouter_model_docs', \App\Services\Ai\AiManager::DEFAULT_DOCS_MODEL);
    $docsFallback = \App\Models\AppSetting::get('openrouter_model_docs_fallback', $currentModel);
    $docsApiKey = \App\Models\AppSetting::get('openrouter_api_key_docs', config('services.openrouter.docs_key', ''));
    $hasDocsApiKey = !empty($docsApiKey);
    $maskedDocsKey = $hasDocsApiKey ? substr($docsApiKey, 0, 8) . '••••' . substr($docsApiKey, -4) : '';
    $docsBaseUrl = \App\Models\AppSetting::get('openrouter_base_url_docs', config('services.openrouter.docs_base_url', 'https://openrouter.ai/api/v1'));
    $defaultFramework = \App\Models\AppSetting::get('default_framework', 'laravel');
    $defaultDialect = \App\Models\AppSetting::get('default_dialect', 'mysql');

    $standardSchemaModels = ['openai/gpt-oss-120b', 'deepseek/deepseek-r1', 'meta-llama/llama-3.3-70b-instruct', 'anthropic/claude-3.7-sonnet', 'openai/gpt-4o'];
    $isCustomSchemaModel = !in_array($currentModel, $standardSchemaModels);

    $standardDocsModels = ['google/gemma-4-31b:free', 'nvidia/nemotron-3.5-lightning:free', 'qwen/qwen3.8-max', 'deepseek/deepseek-r1', 'openai/gpt-oss-120b'];
    $isCustomDocsModel = !in_array($docsModel, $standardDocsModels);
@endphp

@section('content')
<div class="max-w-2xl mx-auto py-3 space-y-8">

    <form id="settings-form" onsubmit="handleSaveSettings(event)" class="space-y-8">
        <!-- Hidden system inputs for backward-compatibility -->
        <input type="hidden" id="openrouter_base_url" name="openrouter_base_url" value="{{ $baseUrl }}">
        <input type="hidden" id="openrouter_base_url_docs" name="openrouter_base_url_docs" value="{{ $docsBaseUrl }}">

        <!-- SECTION 1: KECERDASAN BUATAN (AI) -->
        <section class="space-y-1">
            <div class="flex items-center justify-between pb-2 border-b border-zinc-200 dark:border-zinc-800">
                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Kecerdasan Buatan (AI)</span>
                @if($hasApiKey)
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-emerald-600 dark:text-emerald-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Kunci Aktif
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-zinc-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-zinc-400"></span>
                        Belum Diatur
                    </span>
                @endif
            </div>

            <!-- Row 1: API Key -->
            <div class="py-3 flex flex-col sm:flex-row sm:items-start justify-between gap-3 border-b border-zinc-100 dark:border-zinc-800/50">
                <div class="space-y-0.5">
                    <label for="openrouter_api_key" class="text-xs font-medium text-zinc-900 dark:text-zinc-100">
                        Kunci API AI (Universal)
                    </label>
                    <p class="text-[11px] text-zinc-500 dark:text-zinc-400">
                        Mendukung Claude, ChatGPT, Gemini, DeepSeek, & model gratis
                    </p>
                    <div class="pt-0.5">
                        <a href="https://openrouter.ai/keys" target="_blank" class="text-[11px] text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white underline transition-colors">
                            Dapatkan kunci akses universal ↗
                        </a>
                    </div>
                </div>
                <div class="w-full sm:w-72 shrink-0">
                    <div class="relative">
                        <input type="password" 
                               id="openrouter_api_key" 
                               name="openrouter_api_key" 
                               placeholder="{{ $hasApiKey ? 'Tersimpan (' . $maskedKey . ')' : 'Masukkan kunci API...' }}"
                               class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 focus:border-zinc-400 dark:focus:border-zinc-600 focus:outline-none rounded-lg pl-3 pr-14 py-1.5 text-xs text-zinc-900 dark:text-white font-mono placeholder:text-zinc-400 dark:placeholder:text-zinc-600 transition-colors">
                        <button type="button" 
                                onclick="toggleApiKeyVisibility('openrouter_api_key')" 
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 text-[10px] font-medium px-1.5 py-0.5 rounded transition-colors">
                            Intip
                        </button>
                    </div>
                </div>
            </div>

            <!-- Row 2: Model Generator Skema -->
            <div class="py-3 flex flex-col sm:flex-row sm:items-start justify-between gap-3 border-b border-zinc-100 dark:border-zinc-800/50">
                <div class="space-y-0.5">
                    <label for="openrouter_model_select" class="text-xs font-medium text-zinc-900 dark:text-zinc-100">
                        Model Pembuat Database
                    </label>
                    <p class="text-[11px] text-zinc-500 dark:text-zinc-400">
                        Model AI untuk merancang tabel, relasi, dan arsitektur database
                    </p>
                </div>
                <div class="w-full sm:w-72 shrink-0 space-y-1.5">
                    <select id="openrouter_model_select" 
                            onchange="handleModelSelectChange('openrouter_model_select', 'openrouter_model', 'custom-schema-model-container')"
                            class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 focus:border-zinc-400 dark:focus:border-zinc-600 focus:outline-none rounded-lg px-2.5 py-1.5 text-xs text-zinc-900 dark:text-white font-sans font-medium transition-colors cursor-pointer">
                        <option value="openai/gpt-oss-120b" {{ $currentModel === 'openai/gpt-oss-120b' ? 'selected' : '' }}>GPT-OSS 120B (Default)</option>
                        <option value="deepseek/deepseek-r1" {{ $currentModel === 'deepseek/deepseek-r1' ? 'selected' : '' }}>DeepSeek R1 (Analisis Mendalam)</option>
                        <option value="meta-llama/llama-3.3-70b-instruct" {{ $currentModel === 'meta-llama/llama-3.3-70b-instruct' ? 'selected' : '' }}>Llama 3.3 70B</option>
                        <option value="anthropic/claude-3.7-sonnet" {{ $currentModel === 'anthropic/claude-3.7-sonnet' ? 'selected' : '' }}>Claude 3.7 Sonnet</option>
                        <option value="openai/gpt-4o" {{ $currentModel === 'openai/gpt-4o' ? 'selected' : '' }}>ChatGPT (GPT-4o)</option>
                        @if($isCustomSchemaModel)
                            <option value="{{ $currentModel }}" selected>{{ $currentModel }}</option>
                        @endif
                        <option value="__CUSTOM__">Model Kustom...</option>
                    </select>
                    <div id="custom-schema-model-container" class="{{ $isCustomSchemaModel ? '' : 'hidden' }}">
                        <input type="text" 
                               id="openrouter_model" 
                               name="openrouter_model" 
                               value="{{ $currentModel }}"
                               placeholder="contoh: provider/model:id"
                               class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 focus:border-zinc-400 dark:focus:border-zinc-600 focus:outline-none rounded-lg px-2.5 py-1.5 text-xs text-zinc-900 dark:text-white font-sans font-medium transition-colors">
                    </div>
                </div>
            </div>

            <!-- Row 3: Model Dokumen -->
            <div class="py-3 flex flex-col sm:flex-row sm:items-start justify-between gap-3 border-b border-zinc-100 dark:border-zinc-800/50">
                <div class="space-y-0.5">
                    <label for="openrouter_model_docs_select" class="text-xs font-medium text-zinc-900 dark:text-zinc-100">
                        Model Asisten Dokumen
                    </label>
                    <p class="text-[11px] text-zinc-500 dark:text-zinc-400">
                        Model AI untuk percakapan asisten & perancangan kebutuhan sistem
                    </p>
                </div>
                <div class="w-full sm:w-72 shrink-0 space-y-1.5">
                    <select id="openrouter_model_docs_select" 
                            onchange="handleModelSelectChange('openrouter_model_docs_select', 'openrouter_model_docs', 'custom-docs-model-container')"
                            class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 focus:border-zinc-400 dark:focus:border-zinc-600 focus:outline-none rounded-lg px-2.5 py-1.5 text-xs text-zinc-900 dark:text-white font-sans font-medium transition-colors cursor-pointer">
                        <option value="google/gemma-4-31b:free" {{ $docsModel === 'google/gemma-4-31b:free' ? 'selected' : '' }}>Gemma 4 31B (Gratis :free)</option>
                        <option value="nvidia/nemotron-3.5-lightning:free" {{ $docsModel === 'nvidia/nemotron-3.5-lightning:free' ? 'selected' : '' }}>Nemotron 3.5 (Gratis :free)</option>
                        <option value="qwen/qwen3.8-max" {{ $docsModel === 'qwen/qwen3.8-max' ? 'selected' : '' }}>Qwen 3.8 Max</option>
                        <option value="deepseek/deepseek-r1" {{ $docsModel === 'deepseek/deepseek-r1' ? 'selected' : '' }}>DeepSeek R1</option>
                        <option value="openai/gpt-oss-120b" {{ $docsModel === 'openai/gpt-oss-120b' ? 'selected' : '' }}>GPT-OSS 120B</option>
                        @if($isCustomDocsModel)
                            <option value="{{ $docsModel }}" selected>{{ $docsModel }}</option>
                        @endif
                        <option value="__CUSTOM__">Model Kustom...</option>
                    </select>
                    <div id="custom-docs-model-container" class="{{ $isCustomDocsModel ? '' : 'hidden' }}">
                        <input type="text" 
                               id="openrouter_model_docs" 
                               name="openrouter_model_docs" 
                               value="{{ $docsModel }}"
                               placeholder="contoh: provider/model:id"
                               class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 focus:border-zinc-400 dark:focus:border-zinc-600 focus:outline-none rounded-lg px-2.5 py-1.5 text-xs text-zinc-900 dark:text-white font-sans font-medium transition-colors">
                    </div>
                </div>
            </div>

            <!-- Opsi Lanjutan Tersembunyi -->
            <div class="pt-2">
                <button type="button" 
                        onclick="toggleAdvancedSettings()" 
                        class="text-[11px] text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 inline-flex items-center gap-1.5 transition-colors">
                    <svg id="advanced-chevron" class="w-3 h-3 transform transition-transform duration-200 {{ $hasDocsApiKey ? 'rotate-90' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    <span>Opsi Lanjutan (Kunci Terpisah & Model Cadangan)</span>
                    @if($hasDocsApiKey)
                        <span class="text-[10px] text-emerald-600 dark:text-emerald-400">● Kunci Khusus Aktif</span>
                    @endif
                </button>

                <div id="advanced-settings-panel" class="{{ $hasDocsApiKey ? '' : 'hidden' }} space-y-3 mt-2.5 pt-2.5 pl-3 border-l border-zinc-200 dark:border-zinc-800">
                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <label for="openrouter_api_key_docs" class="text-xs font-medium text-zinc-700 dark:text-zinc-300">
                                Kunci API Khusus Dokumen (Opsional)
                            </label>
                            @if($hasDocsApiKey)
                                <button type="button" onclick="revertToUnifiedDocsKey()" class="text-[10px] text-rose-500 hover:underline">
                                    Hapus & Gunakan Kunci Utama
                                </button>
                            @endif
                        </div>
                        <input type="password" 
                               id="openrouter_api_key_docs" 
                               name="openrouter_api_key_docs" 
                               placeholder="{{ $hasDocsApiKey ? 'Khusus Dokumen aktif (' . $maskedDocsKey . ')' : 'Kosongkan untuk memakai kunci utama' }}" 
                               class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-lg px-2.5 py-1.5 text-xs text-zinc-900 dark:text-white font-mono transition-colors">
                    </div>
                    <div class="space-y-1">
                        <label for="openrouter_model_docs_fallback" class="text-xs font-medium text-zinc-700 dark:text-zinc-300">
                            Model Cadangan Asisten Dokumen
                        </label>
                        <input type="text" 
                               id="openrouter_model_docs_fallback" 
                               name="openrouter_model_docs_fallback" 
                               value="{{ $docsFallback }}" 
                               class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-lg px-2.5 py-1.5 text-xs text-zinc-900 dark:text-white font-sans font-medium transition-colors">
                    </div>
                </div>
            </div>
        </section>

        <!-- SECTION 2: PREFERENSI PROYEK -->
        <section class="space-y-1">
            <div class="pb-2 border-b border-zinc-200 dark:border-zinc-800">
                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Preferensi Proyek</span>
            </div>

            <!-- Row: Framework -->
            <div class="py-3 flex flex-col sm:flex-row sm:items-start justify-between gap-3 border-b border-zinc-100 dark:border-zinc-800/50">
                <div class="space-y-0.5">
                    <label for="default_framework" class="text-xs font-medium text-zinc-900 dark:text-zinc-100">
                        Jenis Framework Bawaan
                    </label>
                    <p class="text-[11px] text-zinc-500 dark:text-zinc-400">
                        Format kode database yang otomatis terpilih saat membuat proyek baru
                    </p>
                </div>
                <div class="w-full sm:w-72 shrink-0">
                    <select id="default_framework" 
                            name="default_framework" 
                            class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 focus:border-zinc-400 dark:focus:border-zinc-600 focus:outline-none rounded-lg px-2.5 py-1.5 text-xs text-zinc-900 dark:text-white transition-colors cursor-pointer">
                        <option value="laravel" {{ $defaultFramework === 'laravel' ? 'selected' : '' }}>Laravel (Eloquent Migrations)</option>
                        <option value="express_prisma" {{ $defaultFramework === 'express_prisma' ? 'selected' : '' }}>Express.js (Prisma Schema)</option>
                        <option value="express_drizzle" {{ $defaultFramework === 'express_drizzle' ? 'selected' : '' }}>Express.js (Drizzle ORM)</option>
                        <option value="springboot_hibernate" {{ $defaultFramework === 'springboot_hibernate' ? 'selected' : '' }}>Spring Boot (JPA / Hibernate)</option>
                        <option value="raw_sql" {{ $defaultFramework === 'raw_sql' ? 'selected' : '' }}>Universal Raw SQL</option>
                    </select>
                </div>
            </div>

            <!-- Row: Dialect -->
            <div class="py-3 flex flex-col sm:flex-row sm:items-start justify-between gap-3 border-b border-zinc-100 dark:border-zinc-800/50">
                <div class="space-y-0.5">
                    <label for="default_dialect" class="text-xs font-medium text-zinc-900 dark:text-zinc-100">
                        Jenis Database Bawaan
                    </label>
                    <p class="text-[11px] text-zinc-500 dark:text-zinc-400">
                        Format SQL bawaan untuk pembuatan tabel
                    </p>
                </div>
                <div class="w-full sm:w-72 shrink-0">
                    <select id="default_dialect" 
                            name="default_dialect" 
                            class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 focus:border-zinc-400 dark:focus:border-zinc-600 focus:outline-none rounded-lg px-2.5 py-1.5 text-xs text-zinc-900 dark:text-white transition-colors cursor-pointer">
                        <option value="mysql" {{ $defaultDialect === 'mysql' ? 'selected' : '' }}>MySQL / MariaDB</option>
                        <option value="pgsql" {{ $defaultDialect === 'pgsql' ? 'selected' : '' }}>PostgreSQL (Supabase)</option>
                        <option value="sqlite" {{ $defaultDialect === 'sqlite' ? 'selected' : '' }}>SQLite</option>
                        <option value="sqlsrv" {{ $defaultDialect === 'sqlsrv' ? 'selected' : '' }}>Microsoft SQL Server</option>
                    </select>
                </div>
            </div>
        </section>

        <!-- SECTION 3: TAMPILAN -->
        <section class="space-y-1">
            <div class="pb-2 border-b border-zinc-200 dark:border-zinc-800">
                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Tampilan Antarmuka</span>
            </div>

            <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-zinc-100 dark:border-zinc-800/50">
                <div class="space-y-0.5">
                    <span class="text-xs font-medium text-zinc-900 dark:text-zinc-100">Tema Visual</span>
                    <p class="text-[11px] text-zinc-500 dark:text-zinc-400">Pilih skema warna terang atau gelap</p>
                </div>
                <div class="inline-flex p-1 rounded-lg bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 gap-1">
                    <button type="button" onclick="setThemeChoice('light')" id="btn-theme-light" 
                            class="px-3 py-1 text-xs rounded-md font-medium transition-all flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <span>Terang</span>
                    </button>
                    <button type="button" onclick="setThemeChoice('dark')" id="btn-theme-dark" 
                            class="px-3 py-1 text-xs rounded-md font-medium transition-all flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                        <span>Gelap</span>
                    </button>
                </div>
                <!-- Hidden inputs for backward-compatibility -->
                <input type="radio" name="app_theme" value="dark" id="theme-radio-dark" class="hidden">
                <input type="radio" name="app_theme" value="light" id="theme-radio-light" class="hidden">
            </div>
        </section>

        <!-- SUBMIT BAR -->
        <div class="pt-3 flex items-center justify-between">
            <div class="text-[11px] text-zinc-400 flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span>SQLite Lokal • Versi 2.0</span>
            </div>
            <button type="submit" 
                    id="save-settings-btn"
                    class="px-4 py-2 bg-zinc-900 hover:bg-black text-white dark:bg-white dark:hover:bg-zinc-200 dark:text-black font-semibold rounded-lg text-xs transition-colors shadow-xs flex items-center gap-2">
                Simpan Pengaturan
            </button>
        </div>

    </form>
</div>
@endsection

@push('scripts')
<script>
function syncThemeButtons() {
    const isDark = document.documentElement.classList.contains('dark');
    const btnDark = document.getElementById('btn-theme-dark');
    const btnLight = document.getElementById('btn-theme-light');
    const darkRadio = document.getElementById('theme-radio-dark');
    const lightRadio = document.getElementById('theme-radio-light');

    if (darkRadio) darkRadio.checked = isDark;
    if (lightRadio) lightRadio.checked = !isDark;

    if (btnDark && btnLight) {
        if (isDark) {
            btnDark.className = "px-3 py-1 text-xs rounded-md font-medium transition-all flex items-center gap-1.5 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-white shadow-2xs";
            btnLight.className = "px-3 py-1 text-xs rounded-md font-medium transition-all flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white";
        } else {
            btnLight.className = "px-3 py-1 text-xs rounded-md font-medium transition-all flex items-center gap-1.5 bg-white text-zinc-900 shadow-2xs";
            btnDark.className = "px-3 py-1 text-xs rounded-md font-medium transition-all flex items-center gap-1.5 text-zinc-500 hover:text-zinc-900";
        }
    }
}

function setThemeChoice(theme) {
    if (window.setTheme) {
        window.setTheme(theme);
    }
    syncThemeButtons();
}

document.addEventListener('DOMContentLoaded', syncThemeButtons);
window.addEventListener('theme-changed', syncThemeButtons);

function toggleApiKeyVisibility(id = 'openrouter_api_key') {
    const input = document.getElementById(id);
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
}

function handleModelSelectChange(selectId, inputId, containerId) {
    const sel = document.getElementById(selectId);
    const container = document.getElementById(containerId);
    const input = document.getElementById(inputId);
    if (!sel || !container || !input) return;

    if (sel.value === '__CUSTOM__') {
        container.classList.remove('hidden');
        input.focus();
    } else {
        container.classList.add('hidden');
        input.value = sel.value;
    }
}

function toggleAdvancedSettings() {
    const panel = document.getElementById('advanced-settings-panel');
    const chevron = document.getElementById('advanced-chevron');
    if (!panel) return;
    const isHidden = panel.classList.contains('hidden');
    if (isHidden) {
        panel.classList.remove('hidden');
        if (chevron) chevron.classList.add('rotate-90');
    } else {
        panel.classList.add('hidden');
        if (chevron) chevron.classList.remove('rotate-90');
    }
}

let shouldClearDocsKey = false;
function revertToUnifiedDocsKey() {
    shouldClearDocsKey = true;
    const input = document.getElementById('openrouter_api_key_docs');
    if (input) {
        input.value = '';
        input.placeholder = 'Kunci khusus dokumen akan dihapus saat disimpan.';
    }
    window.toast('Kunci khusus dokumen ditandai untuk dihapus. Klik "Simpan Pengaturan" untuk menerapkan.', 'info');
}

async function handleSaveSettings(e) {
    e.preventDefault();
    const btn = document.getElementById('save-settings-btn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `Menyimpan...`;

    const apiKeyInput = document.getElementById('openrouter_api_key');
    const frameworkSelect = document.getElementById('default_framework');
    const dialectSelect = document.getElementById('default_dialect');

    // Get selected or custom model for schema generator
    const modelSelect = document.getElementById('openrouter_model_select');
    const modelInput = document.getElementById('openrouter_model');
    const finalModel = (modelSelect && modelSelect.value !== '__CUSTOM__') 
        ? modelSelect.value 
        : (modelInput ? modelInput.value.trim() : '');

    // Get selected or custom model for docs assistant
    const docsModelSelect = document.getElementById('openrouter_model_docs_select');
    const docsModelInput = document.getElementById('openrouter_model_docs');
    const finalDocsModel = (docsModelSelect && docsModelSelect.value !== '__CUSTOM__') 
        ? docsModelSelect.value 
        : (docsModelInput ? docsModelInput.value.trim() : '');

    const payload = {
        openrouter_model: finalModel,
        openrouter_base_url: document.getElementById('openrouter_base_url').value.trim(),
        openrouter_base_url_docs: document.getElementById('openrouter_base_url_docs').value.trim(),
        openrouter_model_docs: finalDocsModel,
        openrouter_model_docs_fallback: document.getElementById('openrouter_model_docs_fallback').value.trim(),
        default_framework: frameworkSelect.value,
        default_dialect: dialectSelect.value,
    };

    if (shouldClearDocsKey) {
        payload.openrouter_api_key_docs = '__CLEAR__';
    } else {
        const docsKeyVal = document.getElementById('openrouter_api_key_docs').value.trim();
        if (docsKeyVal.length > 0) {
            payload.openrouter_api_key_docs = docsKeyVal;
        }
    }

    if (apiKeyInput.value.trim().length > 0) {
        payload.openrouter_api_key = apiKeyInput.value.trim();
    }

    try {
        const result = await window.api('/api/settings', {
            method: 'POST',
            body: payload
        });

        window.toast(result.message || 'Pengaturan berhasil disimpan.', 'success');

        if (payload.openrouter_api_key) {
            const warningBanner = document.getElementById('global-api-warning');
            if (warningBanner) warningBanner.classList.add('hidden');
        }
    } catch (err) {
        // Handled via window.api
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}
</script>
@endpush
