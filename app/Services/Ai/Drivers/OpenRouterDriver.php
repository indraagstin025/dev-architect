<?php

namespace App\Services\Ai\Drivers;

use App\Contracts\AIDriverInterface;
use App\Enums\DatabaseDialect;
use App\Enums\TargetFramework;
use App\Models\AppSetting;
use App\Services\Ai\Prompts\DatabasePromptBuilder;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenRouterDriver implements AIDriverInterface
{
    protected string $baseUrl = 'https://openrouter.ai/api/v1';
    protected ?string $apiKey;
    protected string $model;

    public function __construct()
    {
        // Prioritas API key: dari database AppSetting atau dari .env
        $this->apiKey = AppSetting::get('openrouter_api_key') ?: env('OPENROUTER_API_KEY');
        
        // Model default yang digunakan
        $this->model = AppSetting::get('openrouter_model') ?: env('OPENROUTER_MODEL', 'openai/gpt-oss-120b');
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
        string $targetVersion = '13'
    ): array {
        if (empty($this->apiKey)) {
            throw new RuntimeException(
                "API Key OpenRouter belum diatur. Silakan isi OPENROUTER_API_KEY di file .env atau menu Settings."
            );
        }

        $systemPrompt = DatabasePromptBuilder::buildSystemPrompt($framework, $dialect, $targetVersion);

        $response = Http::timeout(180)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'HTTP-Referer' => 'https://devarchitect.local',
                'X-Title' => 'Laravel AI Migration & ERD Generator',
                'Content-Type' => 'application/json',
            ])
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.2,
            ]);

        if ($response->failed()) {
            $errorData = $response->json();
            $errorMessage = $errorData['error']['message'] ?? $response->body();
            throw new RuntimeException("OpenRouter API Error: {$errorMessage}");
        }

        $content = $response->json('choices.0.message.content');

        if (empty($content)) {
            throw new RuntimeException("Respon kosong dari OpenRouter.");
        }

        // Bersihkan pembungkus markdown ```json ... ``` bila disertakan oleh model
        $cleanJson = $this->cleanMarkdownJson($content);
        $parsed = json_decode($cleanJson, true);

        if (!is_array($parsed) || !isset($parsed['migration_files'])) {
            throw new RuntimeException("Respon AI tidak sesuai format JSON yang diharapkan: " . substr($content, 0, 300));
        }

        return [
            'erd_mermaid_text' => $parsed['erd_mermaid_text'] ?? '',
            'migration_files' => $parsed['migration_files'] ?? [],
        ];
    }

    /**
     * Membersihkan pembungkus ```json dan ``` jika ada.
     */
    protected function cleanMarkdownJson(string $raw): string
    {
        $cleaned = trim($raw);
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
        $cleaned = preg_replace('/\s*```$/i', '', $cleaned);
        return trim($cleaned);
    }
}
