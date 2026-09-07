<?php

namespace Tests\Unit;

use App\Services\ScaffoldProjectService;
use Tests\TestCase;

class ScaffoldPlanTest extends TestCase
{
    protected string $parent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parent = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'scaffold_plan_' . uniqid();
        mkdir($this->parent, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->parent);
        parent::tearDown();
    }

    protected function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function test_plan_laravel_uses_composer_create_project(): void
    {
        $service = new ScaffoldProjectService();
        $plan = $service->plan('laravel', 'toko-bekas', $this->parent);

        $this->assertStringEndsWith('toko-bekas', $plan['target']);
        $cmds = array_column(array_filter($plan['steps'], fn ($s) => $s['kind'] === 'process'), 'cmd');
        $this->assertContains(['composer', 'create-project', 'laravel/laravel', 'toko-bekas', '--prefer-dist', '--no-interaction'], $cmds);
    }

    public function test_plan_express_uses_stub_and_npm_install(): void
    {
        $service = new ScaffoldProjectService();
        $plan = $service->plan('express_prisma', 'api-bekas', $this->parent);

        $kinds = array_column($plan['steps'], 'kind');
        $this->assertContains('copy-stub', $kinds);
        $this->assertContains('process', $kinds);

        $npm = null;
        foreach ($plan['steps'] as $step) {
            if ($step['kind'] === 'process') {
                $npm = $step;
            }
        }
        $this->assertEquals(['npm', 'install', '--no-audit', '--no-fund'], $npm['cmd']);
        $this->assertEquals($plan['target'], $npm['cwd']);
    }

    public function test_plan_spring_validates_coords_without_network(): void
    {
        $service = new ScaffoldProjectService();
        $plan = $service->plan('springboot_hibernate', 'toko-bekas', $this->parent, [
            'spring_group' => 'com.example',
            'spring_artifact' => 'toko-bekas',
        ]);

        $dl = null;
        foreach ($plan['steps'] as $step) {
            if ($step['kind'] === 'download-spring') {
                $dl = $step;
            }
        }
        $this->assertNotNull($dl);
        $this->assertEquals('com.example', $dl['group']);

        $this->expectException(\RuntimeException::class);
        $service->plan('springboot_hibernate', 'toko-bekas', $this->parent, [
            'spring_group' => 'Bad Group!!',
            'spring_artifact' => 'toko-bekas',
        ]);
    }

    public function test_plan_rejects_bad_slug_and_existing_target(): void
    {
        $service = new ScaffoldProjectService();

        $this->expectException(\RuntimeException::class);
        $service->plan('laravel', 'Nama Salah!!', $this->parent);
    }

    public function test_plan_rejects_existing_target(): void
    {
        mkdir($this->parent . DIRECTORY_SEPARATOR . 'sudah-ada');

        $service = new ScaffoldProjectService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/sudah ada/');
        $service->plan('raw_sql', 'sudah-ada', $this->parent);
    }

    public function test_plan_rejects_unknown_template(): void
    {
        $service = new ScaffoldProjectService();

        $this->expectException(\RuntimeException::class);
        $service->plan('codeigniter', 'toko-bekas', $this->parent);
    }

    public function test_missing_parent_is_auto_created_one_level(): void
    {
        $missing = $this->parent . DIRECTORY_SEPARATOR . 'workspace-baru';

        $service = new ScaffoldProjectService();
        $plan = $service->plan('raw_sql', 'toko-bekas', $missing);

        $this->assertDirectoryExists($missing);
        $this->assertEquals($missing, $plan['parent']);
    }

    public function test_deeply_nested_missing_parent_is_rejected(): void
    {
        $service = new ScaffoldProjectService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/tidak valid atau tidak bisa ditulis/');
        $service->plan(
            'raw_sql',
            'toko-bekas',
            $this->parent . DIRECTORY_SEPARATOR . 'typo' . DIRECTORY_SEPARATOR . 'berlapis'
        );
    }
}
