<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingRequest;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    /**
     * Masking seragam untuk API key (jangan pernah kirim utuh ke frontend).
     */
    protected function maskKey(string $key): string
    {
        return ! empty($key) ? substr($key, 0, 8) . '...' . substr($key, -4) : '';
    }

    /**
     * Mengambil daftar pengaturan yang sedang aktif (dengan masking pada API Key).
     */
    public function index(): JsonResponse
    {
        $apiKey = AppSetting::get('openrouter_api_key', config('services.openrouter.key', ''));
        $docsKey = AppSetting::get('openrouter_api_key_docs', config('services.openrouter.docs_key', ''));

        return response()->json([
            'success' => true,
            'data' => [
                'has_api_key' => !empty($apiKey),
                'masked_api_key' => $this->maskKey((string) $apiKey),
                'model' => AppSetting::get('openrouter_model', config('services.openrouter.model', 'openai/gpt-oss-120b')),
                'base_url' => AppSetting::get('openrouter_base_url', config('services.openrouter.base_url', 'https://openrouter.ai/api/v1')),
                'has_api_key_docs' => !empty($docsKey),
                'masked_api_key_docs' => $this->maskKey((string) $docsKey),
                'base_url_docs' => AppSetting::get('openrouter_base_url_docs', config('services.openrouter.docs_base_url', 'https://openrouter.ai/api/v1')),
                'model_docs' => AppSetting::get('openrouter_model_docs', \App\Services\Ai\AiManager::DEFAULT_DOCS_MODEL),
                'model_docs_fallback' => AppSetting::get('openrouter_model_docs_fallback', AppSetting::get('openrouter_model', config('services.openrouter.model', 'openai/gpt-oss-120b'))),
                'default_framework' => AppSetting::get('default_framework', 'laravel'),
                'default_dialect' => AppSetting::get('default_dialect', 'mysql'),
            ],
        ]);
    }

    /**
     * Menyimpan pengaturan baru menggunakan validasi UpdateSettingRequest.
     */
    public function update(UpdateSettingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (!empty($validated['openrouter_api_key'])) {
            AppSetting::set('openrouter_api_key', $validated['openrouter_api_key'], true);
        }

        if (!empty($validated['openrouter_model'])) {
            AppSetting::set('openrouter_model', $validated['openrouter_model']);
        }

        if (!empty($validated['openrouter_base_url'])) {
            AppSetting::set('openrouter_base_url', rtrim($validated['openrouter_base_url'], '/'));
        }

        if (!empty($validated['openrouter_model_docs'])) {
            AppSetting::set('openrouter_model_docs', $validated['openrouter_model_docs']);
        }

        if (array_key_exists('openrouter_api_key_docs', $validated)) {
            if ($validated['openrouter_api_key_docs'] === '__CLEAR__') {
                AppSetting::set('openrouter_api_key_docs', '');
            } elseif (!empty($validated['openrouter_api_key_docs'])) {
                AppSetting::set('openrouter_api_key_docs', $validated['openrouter_api_key_docs'], true);
            }
        }

        if (!empty($validated['openrouter_base_url_docs'])) {
            AppSetting::set('openrouter_base_url_docs', rtrim($validated['openrouter_base_url_docs'], '/'));
        }

        if (array_key_exists('openrouter_model_docs_fallback', $validated)) {
            AppSetting::set('openrouter_model_docs_fallback', $validated['openrouter_model_docs_fallback'] ?? '');
        }

        if (!empty($validated['default_framework'])) {
            AppSetting::set('default_framework', $validated['default_framework']);
        }

        if (!empty($validated['default_dialect'])) {
            AppSetting::set('default_dialect', $validated['default_dialect']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan berhasil diperbarui.',
        ]);
    }
}
