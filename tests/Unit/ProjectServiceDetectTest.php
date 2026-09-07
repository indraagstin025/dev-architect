<?php

namespace Tests\Unit;

use App\Enums\TargetFramework;
use App\Services\ProjectService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProjectServiceDetectTest extends TestCase
{
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'devarchitect_detect_test_' . uniqid();
        File::makeDirectory($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }
        parent::tearDown();
    }

    public function test_detects_drizzle_from_dependencies(): void
    {
        $pkg = [
            'name' => 'my-drizzle-app',
            'dependencies' => ['drizzle-orm' => '^0.30.0', 'express' => '^4.18.0'],
        ];
        File::put($this->tempDir . '/package.json', json_encode($pkg));

        $service = new ProjectService();
        $framework = $service->detectFramework($this->tempDir);

        $this->assertEquals(TargetFramework::EXPRESS_DRIZZLE, $framework);
    }

    public function test_detects_prisma_from_dependencies(): void
    {
        $pkg = [
            'name' => 'my-prisma-app',
            'dependencies' => ['@prisma/client' => '^5.0.0'],
        ];
        File::put($this->tempDir . '/package.json', json_encode($pkg));

        $service = new ProjectService();
        $framework = $service->detectFramework($this->tempDir);

        $this->assertEquals(TargetFramework::EXPRESS_PRISMA, $framework);
    }

    public function test_falls_back_to_raw_sql_for_generic_node_project(): void
    {
        $pkg = [
            'name' => 'my-mongoose-app',
            'dependencies' => ['mongoose' => '^8.0.0', 'express' => '^4.18.0'],
        ];
        File::put($this->tempDir . '/package.json', json_encode($pkg));

        $service = new ProjectService();
        $framework = $service->detectFramework($this->tempDir);

        $this->assertEquals(TargetFramework::RAW_SQL, $framework);
    }

    public function test_handles_invalid_package_json_safely(): void
    {
        File::put($this->tempDir . '/package.json', '{ broken-json ...');

        $service = new ProjectService();
        $framework = $service->detectFramework($this->tempDir);

        $this->assertEquals(TargetFramework::RAW_SQL, $framework);
    }

    public function test_detects_laravel_from_composer_require_without_artisan(): void
    {
        $composer = [
            'name' => 'vendor/laravel-clone',
            'require' => ['laravel/framework' => '^11.0'],
        ];
        File::put($this->tempDir . '/composer.json', json_encode($composer));

        $service = new ProjectService();
        $framework = $service->detectFramework($this->tempDir);

        $this->assertEquals(TargetFramework::LARAVEL, $framework);
    }
}
