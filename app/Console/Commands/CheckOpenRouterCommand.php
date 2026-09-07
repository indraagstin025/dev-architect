<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckOpenRouterCommand extends Command
{
    protected $signature = 'openrouter:check {--model= : Uji kirim pesan chat ke model tertentu}';
    protected $description = 'Periksa validitas API Key OpenRouter dari .env dan daftar model yang tersedia';

    public function handle(): int
    {
        $this->info('========================================================================');
        $this->info('  DEVArchitect - Pemeriksa API Key & Katalog Model OpenRouter');
        $this->info('========================================================================');

        $keyGen = env('OPENROUTER_API_KEY');
        $keyDocs = env('OPENROUTER_API_KEY_DOCS');

        $this->checkSingleKey('1. OPENROUTER_API_KEY (Skema / Generator)', $keyGen, env('OPENROUTER_MODEL', 'openai/gpt-oss-120b'));
        $this->newLine();
        $this->checkSingleKey('2. OPENROUTER_API_KEY_DOCS (Asisten Dokumen)', $keyDocs, 'nvidia/nemotron-3.5-lightning:free');

        return Command::SUCCESS;
    }

    protected function checkSingleKey(string $title, ?string $key, ?string $defaultModel): void
    {
        $this->line("<fg=yellow;options=bold>▶ {$title}</>");
        if (empty($key)) {
            $this->warn('  ⚠️ API Key kosong di .env');
            return;
        }

        $masked = substr($key, 0, 14) . '...' . substr($key, -4);
        $this->line("  Key: <fg=cyan>{$masked}</>");

        // 1. Cek info status & usage
        try {
            $authRes = Http::timeout(12)->withToken($key)->get('https://openrouter.ai/api/v1/auth/key');
            if ($authRes->successful()) {
                $d = $authRes->json('data', []);
                $usage = '$' . number_format((float) ($d['usage'] ?? 0), 6);
                $limit = $d['limit'] ? '$' . $d['limit'] : 'Tanpa batas (Unlimited)';
                $freeTier = !empty($d['is_free_tier']) ? 'Ya (Free Tier)' : 'Berbayar (Paid Tier)';
                $this->line("  Status: <fg=green;options=bold>VALID</> | Label: <fg=white>{$d['label']}</>");
                $this->line("  Usage: <fg=white>{$usage}</> | Limit: <fg=white>{$limit}</> | Akun: <fg=white>{$freeTier}</>");
            } else {
                $this->error("  Gagal auth/key: HTTP {$authRes->status()} - {$authRes->body()}");
            }
        } catch (\Throwable $e) {
            $this->error("  Koneksi auth error: {$e->getMessage()}");
        }

        // 2. Cek Model
        try {
            $modelsRes = Http::timeout(15)->withToken($key)->get('https://openrouter.ai/api/v1/models');
            if ($modelsRes->successful()) {
                $data = $modelsRes->json('data', []);
                $this->line("  Total Model Tersedia: <fg=green>" . count($data) . " model</>");

                // Filter Free
                $free = array_values(array_filter($data, fn ($m) => str_ends_with($m['id'] ?? '', ':free') || ($m['pricing']['prompt'] ?? '') === '0'));
                $this->line("  Model Gratis Aktif (:free): <fg=cyan>" . count($free) . " model</>");

                $freeSamples = array_slice($free, 0, 6);
                foreach ($freeSamples as $fm) {
                    $this->line("    • <fg=white>{$fm['id']}</> (" . ($fm['name'] ?? '') . ")");
                }

                // Filter GPT-OSS
                $gptOss = array_values(array_filter($data, fn ($m) => str_contains(strtolower($m['id'] ?? ''), 'gpt-oss')));
                if (!empty($gptOss)) {
                    $this->line("  Model GPT-OSS Tersedia:");
                    foreach ($gptOss as $gm) {
                        $this->line("    • <fg=magenta>{$gm['id']}</> (" . ($gm['name'] ?? '') . ")");
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->error("  Gagal memuat katalog model: {$e->getMessage()}");
        }

        // 3. Test Ping Chat jika model dipilih
        $testModel = $this->option('model') ?: $defaultModel;
        if ($testModel) {
            $this->line("  Menguji panggilan chat ke [<fg=yellow>{$testModel}</>]...");
            try {
                $chatRes = Http::timeout(20)->withToken($key)->post('https://openrouter.ai/api/v1/chat/completions', [
                    'model' => $testModel,
                    'messages' => [['role' => 'user', 'content' => 'Tes koneksi singkat. Balas OK.']],
                    'max_tokens' => 15,
                ]);
                if ($chatRes->successful()) {
                    $reply = trim($chatRes->json('choices.0.message.content', ''));
                    $tokens = $chatRes->json('usage.total_tokens', 0);
                    $this->line("  <fg=green;options=bold>✓ Berhasil merespons:</> \"{$reply}\" ({$tokens} tokens)");
                } else {
                    $err = $chatRes->json('error.message') ?: $chatRes->body();
                    $this->warn("  ⚠️ Gagal panggil model: {$err}");
                }
            } catch (\Throwable $e) {
                $this->error("  Panggilan chat error: {$e->getMessage()}");
            }
        }
    }
}
