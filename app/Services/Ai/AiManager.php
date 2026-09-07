<?php

namespace App\Services\Ai;

use App\Contracts\AIDriverInterface;
use App\Models\AppSetting;
use App\Services\Ai\Drivers\OpenRouterDriver;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class AiManager
{
    public const DEFAULT_DOCS_MODEL = 'nvidia/nemotron-3.5-lightning:free';

    /**
     * Mendapatkan instance driver AI yang sedang aktif.
     */
    public function driver(?string $name = null): AIDriverInterface
    {
        $driverName = $name ?? AppSetting::get('active_ai_driver', 'openrouter');

        return match (strtolower($driverName)) {
            'openrouter' => app(OpenRouterDriver::class),
            default => throw new InvalidArgumentException("Driver AI '{$driverName}' tidak didukung."),
        };
    }

    /**
     * Driver khusus alur dokumen: key OpenRouter sendiri (OPENROUTER_API_KEY_DOCS),
     * terpisah dari key endpoint skema. Fallback ke key skema bila docs-key kosong.
     */
    public function docsDriver(): AIDriverInterface
    {
        $docsKey = AppSetting::get('openrouter_api_key_docs')
            ?: config('services.openrouter.docs_key');
        $docsBaseUrl = AppSetting::get('openrouter_base_url_docs')
            ?: config('services.openrouter.docs_base_url', 'https://openrouter.ai/api/v1');

        return app(OpenRouterDriver::class, [
            'apiKey' => $docsKey ?: null,
            'baseUrl' => $docsBaseUrl,
            'model' => $this->docsModel()
        ]);
    }

    /**
     * Chat dokumen dengan key yang benar per endpoint: primer via docsDriver
     * (docs-key), fallback via driver skema (schema-key).
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @return array{0: array{content: string, prompt_tokens: int, completion_tokens: int}, 1: string}
     */
    public function docsChat(
        array $messages,
        ?string $primary = null,
        float $temperature = 0.7,
        int $maxTokens = 4000
    ): array {
        $primary ??= $this->docsModel();

        try {
            $result = $this->docsDriver()->chat($messages, $primary, $temperature, $maxTokens);

            return [$result, $primary];
        } catch (Throwable $first) {
            $fallback = $this->docsFallbackModel();

            if (! $fallback || $fallback === $primary) {
                throw $first;
            }

            Log::warning("Model dokumen [{$primary}] gagal, fallback ke [{$fallback}]: " . $first->getMessage());

            $result = $this->driver()->chat($messages, $fallback, $temperature, $maxTokens);

            return [$result, $fallback];
        }
    }

    /**
     * Preset model untuk picker chatbot dokumen.
     *
     * @return array<int, array{id: string, label: string, free: bool}>
     */
    public static function docModelPresets(): array
    {
        return [
            ['id' => self::DEFAULT_DOCS_MODEL, 'label' => 'Nemotron 3.5 Lightning (Gratis)', 'free' => true],
            ['id' => 'openai/gpt-oss-120b', 'label' => 'GPT-OSS 120B (Skema & Penalaran)', 'free' => false],
            ['id' => 'qwen/qwen3.8-max', 'label' => 'Qwen 3.8 Max (Bahasa Indonesia)', 'free' => false],
            ['id' => 'deepseek/deepseek-v4-flash', 'label' => 'DeepSeek V4 Flash (Hemat)', 'free' => false],
        ];
    }

    /**
     * Katalog live OpenRouter (cache 24 jam). Gagal/offline → daftar kosong.
     *
     * @return array{models: array<int, array{id: string}>, cached_at: ?string}
     */
    public function liveModelCatalog(bool $refresh = false): array
    {
        $cacheKey = 'model_catalog_cache';

        if (! $refresh) {
            $cached = AppSetting::get($cacheKey);
            $decoded = is_string($cached) ? json_decode($cached, true) : null;
            if (is_array($decoded) && isset($decoded['models'])) {
                return $decoded;
            }
        }

        $result = ['models' => [], 'cached_at' => null];

        try {
            $apiKey = AppSetting::get('openrouter_api_key_docs')
                ?: config('services.openrouter.docs_key')
                ?: AppSetting::get('openrouter_api_key')
                ?: config('services.openrouter.key');

            if (empty($apiKey)) {
                return $result;
            }

            $response = \Illuminate\Support\Facades\Http::timeout(20)
                ->withToken($apiKey)
                ->get('https://openrouter.ai/api/v1/models');

            if ($response->ok()) {
                $raw = collect($response->json('data', []));

                // Prioritaskan model gratis (:free) dan GPT-OSS di atas
                $freeModels = $raw->filter(fn ($m) => str_ends_with($m['id'] ?? '', ':free') || ($m['pricing']['prompt'] ?? '') === '0');
                $gptOssModels = $raw->filter(fn ($m) => str_contains(strtolower($m['id'] ?? ''), 'gpt-oss'));
                $otherModels = $raw->reject(fn ($m) => str_ends_with($m['id'] ?? '', ':free') || str_contains(strtolower($m['id'] ?? ''), 'gpt-oss'));

                $models = $freeModels->merge($gptOssModels)->merge($otherModels)
                    ->pluck('id')
                    ->filter(fn ($id) => is_string($id) && $id !== '')
                    ->take(100)
                    ->map(fn ($id) => ['id' => $id])
                    ->values()
                    ->all();

                $result = ['models' => $models, 'cached_at' => now()->toIso8601String()];
                AppSetting::set($cacheKey, json_encode($result));
            }
        } catch (Throwable $e) {
            Log::warning('Gagal memuat katalog model OpenRouter: ' . $e->getMessage());
        }

        return $result;
    }
    public function docsModel(): string
    {
        return (string) (
            AppSetting::get('openrouter_model_docs')
                ?: config('services.openrouter.docs_model')
                ?: self::DEFAULT_DOCS_MODEL
        );
    }

    /**
     * Model fallback bila model dokumen gagal (null = tanpa fallback, gagal jelas).
     */
    public function docsFallbackModel(): ?string
    {
        $fallback = AppSetting::get(
            'openrouter_model_docs_fallback',
            AppSetting::get('openrouter_model', config('services.openrouter.model', 'openai/gpt-oss-120b'))
        );

        return $fallback ? (string) $fallback : null;
    }

    /**
     * Jalankan task AI dengan rantai: model dokumen -> fallback -> lempar error awal.
     *
     * @template T
     * @param callable(string|null): T $task menerima nama model (null = default driver)
     * @param string|null $primary override model utama (mis. pilihan per proyek, Q18)
     * @return T
     */
    public function withDocsModelFallback(callable $task, ?string $primary = null): mixed
    {
        return $this->withDocsModelFallbackResult($task, $primary)[0];
    }

    /**
     * Varian yang juga melaporkan model mana yang sukses dipakai.
     *
     * @template T
     * @param callable(string|null): T $task
     * @return array{0: T, 1: string}
     */
    public function withDocsModelFallbackResult(callable $task, ?string $primary = null): array
    {
        $primary ??= $this->docsModel();

        try {
            return [$task($primary), $primary];
        } catch (Throwable $first) {
            $fallback = $this->docsFallbackModel();

            if (! $fallback || $fallback === $primary) {
                throw $first;
            }

            Log::warning("Model dokumen [{$primary}] gagal, fallback ke [{$fallback}]: " . $first->getMessage());

            return [$task($fallback), $fallback];
        }
    }
}
