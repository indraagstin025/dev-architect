<?php

namespace Tests\Unit;

use App\Models\AppSetting;
use App\Services\Ai\AiManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiManagerDocsModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_docs_model_defaults_to_free_endpoint(): void
    {
        $manager = new AiManager();

        $this->assertEquals(AiManager::DEFAULT_DOCS_MODEL, $manager->docsModel());
    }

    public function test_docs_fallback_defaults_to_schema_model(): void
    {
        AppSetting::set('openrouter_model', 'openai/gpt-oss-120b');

        $manager = new AiManager();

        $this->assertEquals('openai/gpt-oss-120b', $manager->docsFallbackModel());
    }

    public function test_with_fallback_returns_primary_result_without_calling_fallback(): void
    {
        $manager = new AiManager();
        $fallbackCalls = 0;

        $result = $manager->withDocsModelFallback(function (?string $model) use ($manager, &$fallbackCalls) {
            if ($model !== $manager->docsModel()) {
                $fallbackCalls++;
            }

            return 'primary-ok';
        });

        $this->assertEquals('primary-ok', $result);
        $this->assertEquals(0, $fallbackCalls);
    }

    public function test_with_fallback_uses_fallback_on_primary_failure(): void
    {
        AppSetting::set('openrouter_model_docs_fallback', 'fallback/model');

        $manager = new AiManager();
        $seen = [];

        $result = $manager->withDocsModelFallback(function (?string $model) use (&$seen) {
            $seen[] = $model;

            if ($model !== 'fallback/model') {
                throw new \RuntimeException('gratis habis (429)');
            }

            return 'fallback-ok';
        });

        $this->assertEquals('fallback-ok', $result);
        $this->assertEquals([$manager->docsModel(), 'fallback/model'], $seen);
    }

    public function test_with_fallback_rethrows_first_error_when_no_fallback(): void
    {
        AppSetting::set('openrouter_model_docs_fallback', '');

        $manager = new AiManager();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('gagal total');

        $manager->withDocsModelFallback(function () {
            throw new \RuntimeException('gagal total');
        });
    }
}
