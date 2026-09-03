<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Mengambil daftar pengaturan yang sedang aktif (dengan masking pada API Key).
     */
    public function index(): JsonResponse
    {
        $apiKey = AppSetting::get('openrouter_api_key', env('OPENROUTER_API_KEY', ''));
        $maskedKey = !empty($apiKey) ? substr($apiKey, 0, 8) . '...' . substr($apiKey, -4) : '';

        return response()->json([
            'success' => true,
            'data' => [
                'has_api_key' => !empty($apiKey),
                'masked_api_key' => $maskedKey,
                'model' => AppSetting::get('openrouter_model', env('OPENROUTER_MODEL', 'openai/gpt-oss-120b')),
                'default_framework' => AppSetting::get('default_framework', 'laravel'),
                'default_dialect' => AppSetting::get('default_dialect', 'mysql'),
            ],
        ]);
    }

    /**
     * Menyimpan pengaturan baru.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'openrouter_api_key' => ['nullable', 'string'],
            'openrouter_model' => ['nullable', 'string'],
            'default_framework' => ['nullable', 'string'],
            'default_dialect' => ['nullable', 'string'],
        ]);

        if (!empty($validated['openrouter_api_key'])) {
            AppSetting::set('openrouter_api_key', $validated['openrouter_api_key'], true);
        }

        if (!empty($validated['openrouter_model'])) {
            AppSetting::set('openrouter_model', $validated['openrouter_model']);
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
