<?php

namespace App\Services\Ai\Drivers;

use App\Contracts\AIDriverInterface;
use App\Enums\DatabaseDialect;
use App\Enums\TargetFramework;
use App\Models\AppSetting;
use App\Services\Ai\Prompts\DatabasePromptBuilder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;

class OpenRouterDriver implements AIDriverInterface
{
    protected string $baseUrl;
    protected ?string $apiKey;
    protected string $model;

    public function __construct(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        ?string $model = null
    ) {
        // Prioritas: argumen eksplisit (mis. driver dokumen) → setting DB → config/.env.
        $this->baseUrl = rtrim(
            $baseUrl
                ?: AppSetting::get('openrouter_base_url')
                ?: config('services.openrouter.base_url', 'https://openrouter.ai/api/v1'),
            '/'
        );

        // Prioritas API key: dari database AppSetting atau dari config/services
        $this->apiKey = $apiKey
            ?: AppSetting::get('openrouter_api_key')
            ?: config('services.openrouter.key');

        // Model default yang digunakan
        $this->model = $model
            ?: AppSetting::get('openrouter_model')
            ?: config('services.openrouter.model', 'openai/gpt-oss-120b');
    }

    /**
     * Men-generate ERD Mermaid dan file skema/migrasi menggunakan OpenRouter.
     *
     * @return array{
     *     erd_mermaid_text: string,
     *     migration_files: array<array{filename: string, content: string}>
     * }
     */
    public function generate(
        string $prompt, 
        TargetFramework $framework = TargetFramework::LARAVEL,
        DatabaseDialect $dialect = DatabaseDialect::MYSQL, 
        string $targetVersion = '13',
        ?string $model = null
    ): array {
        if (empty($this->apiKey)) {
            throw new RuntimeException(
                "API Key OpenRouter belum diatur. Silakan isi OPENROUTER_API_KEY di file .env atau menu Settings."
            );
        }

        $effectiveModel = $model ?: $this->model;
        $systemPrompt = DatabasePromptBuilder::buildSystemPrompt($framework, $dialect, $targetVersion);

        try {
            $response = Http::timeout(60)
                ->connectTimeout(10)
                ->retry(2, 500)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'HTTP-Referer' => 'https://devarchitect.local',
                    'X-Title' => 'DEVArchitect Universal Database Architect',
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $effectiveModel,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        // Input user selalu dibungkus delimiter agar model
                        // memperlakukannya sebagai DATA, bukan instruksi.
                        ['role' => 'user', 'content' => DatabasePromptBuilder::wrapUserPrompt($prompt)],
                    ],
                    'temperature' => 0.2,
                ]);

        } catch (\Throwable $e) {
            Log::error('OpenRouter HTTP connection error: ' . $e->getMessage());
            throw new RuntimeException("Gagal terhubung ke layanan OpenRouter: " . $e->getMessage());
        }

        if ($response->failed()) {
            $this->handleFailedResponse($response);
        }

        $content = $response->json('choices.0.message.content');

        if (empty($content)) {
            throw new RuntimeException("Respon kosong dari model OpenRouter.");
        }

        // Ekstraksi dan sanitasi JSON
        $parsed = $this->parseJsonResponse($content);

        if (!isset($parsed['migration_files']) || !is_array($parsed['migration_files'])) {
            throw new RuntimeException("Format respon AI tidak memuat array berkas skema yang valid.");
        }

        // Batasi maksimal 30 file
        $files = array_slice($parsed['migration_files'], 0, 30);

        return [
            'erd_mermaid_text' => $parsed['erd_mermaid_text'] ?? '',
            'migration_files' => $files,
        ];
    }

    /**
     * Tangani respon gagal dari OpenRouter dengan pesan yang jelas (Rate Limit, Quota, dsb).
     */
    protected function handleFailedResponse(\Illuminate\Http\Client\Response $response): void
    {
        $errorData = $response->json();
        $rawError = $errorData['error']['message'] ?? '';
        $status = $response->status();

        if ($status === 429 || str_contains(strtolower($rawError), 'rate limit')) {
            $errorMessage = "Batas kecepatan tercapai (Rate Limit 429: maks 20 req/menit). Tunggu 1 menit atau pilih model AI lain. [Detail: {$rawError}]";
        } elseif ($status === 402 || str_contains(strtolower($rawError), 'credit') || str_contains(strtolower($rawError), 'quota') || str_contains(strtolower($rawError), 'daily')) {
            $errorMessage = "Batas kuota harian tercapai (Daily limit 50 req/hari atau saldo tidak cukup). Tunggu reset besok atau pilih model AI lain. [Detail: {$rawError}]";
        } else {
            $errorMessage = $rawError ?: "Status {$status}: " . substr($response->body(), 0, 300);
        }

        Log::error("OpenRouter API error response: {$errorMessage}");

        throw new RuntimeException("OpenRouter API Error: {$errorMessage}");
    }

    /**
     * Percakapan bebas untuk fitur dokumen (Markdown, bukan JSON skema).
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @return array{content: string, prompt_tokens: int, completion_tokens: int}
     */
    public function chat(
        array $messages,
        ?string $model = null,
        float $temperature = 0.7,
        int $maxTokens = 4000
    ): array {
        if (empty($this->apiKey)) {
            throw new RuntimeException(
                "API Key OpenRouter belum diatur. Silakan isi OPENROUTER_API_KEY di file .env atau menu Settings."
            );
        }

        try {
            $response = Http::timeout(180)
                ->connectTimeout(10)
                ->retry(1, 1000)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'HTTP-Referer' => 'https://devarchitect.local',
                    'X-Title' => 'DEVArchitect Document Assistant',
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $model ?: $this->model,
                    'messages' => $messages,
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                ]);
        } catch (\Throwable $e) {
            Log::error('OpenRouter HTTP connection error: ' . $e->getMessage());
            throw new RuntimeException("Gagal terhubung ke layanan OpenRouter: " . $e->getMessage());
        }

        if ($response->failed()) {
            $this->handleFailedResponse($response);
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException("Respon kosong dari model OpenRouter.");
        }

        $usage = $response->json('usage', []);

        return [
            'content' => $content,
            'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
        ];
    }

    /**
     * Mengekstrak dan mem-parse substring JSON murni dari teks respon AI.
     */
    protected function parseJsonResponse(string $raw): array
    {
        $firstBrace = strpos($raw, '{');
        $lastBrace = strrpos($raw, '}');

        if ($firstBrace === false || $lastBrace === false || $lastBrace < $firstBrace) {
            throw new RuntimeException("Respon AI tidak memuat struktur objek JSON yang valid.");
        }

        $jsonSubstring = substr($raw, $firstBrace, $lastBrace - $firstBrace + 1);

        try {
            return json_decode($jsonSubstring, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Log::error("Gagal mendecode JSON dari AI: " . $e->getMessage(), ['raw' => substr($raw, 0, 500)]);
            throw new RuntimeException("Respon AI menghasilkan format JSON yang rusak: " . $e->getMessage());
        }
    }
}
