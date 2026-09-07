<?php

namespace Tests\Feature;

use App\Enums\DatabaseDialect;
use App\Enums\TargetFramework;
use App\Models\AppSetting;
use App\Models\Project;
use App\Services\Parsers\PrismaSchemaParser;
use App\Services\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProjectDialectDetectionAndParserTest extends TestCase
{
    use RefreshDatabase;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'devarch_test_' . uniqid();
        File::makeDirectory($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }
        parent::tearDown();
    }

    public function test_detect_database_dialect_from_prisma_schema(): void
    {
        $prismaDir = $this->tempDir . DIRECTORY_SEPARATOR . 'prisma';
        File::makeDirectory($prismaDir, 0755, true);

        $schemaContent = <<<'PRISMA'
datasource db {
  provider = "postgresql"
  url      = env("DATABASE_URL")
}

generator client {
  provider = "prisma-client-js"
}

model User {
  id    Int     @id @default(autoincrement())
  email String  @unique
  name  String?
}
PRISMA;

        File::put($prismaDir . DIRECTORY_SEPARATOR . 'schema.prisma', $schemaContent);

        /** @var ProjectService $service */
        $service = app(ProjectService::class);
        $dialect = $service->detectDatabaseDialect($this->tempDir);

        $this->assertEquals(DatabaseDialect::POSTGRESQL, $dialect);
    }

    public function test_detect_database_dialect_from_env_file(): void
    {
        $envContent = <<<'ENV'
APP_NAME=Laravel
DB_CONNECTION=sqlite
DB_DATABASE=/database/database.sqlite
ENV;

        File::put($this->tempDir . DIRECTORY_SEPARATOR . '.env', $envContent);

        /** @var ProjectService $service */
        $service = app(ProjectService::class);
        $dialect = $service->detectDatabaseDialect($this->tempDir);

        $this->assertEquals(DatabaseDialect::SQLITE, $dialect);
    }

    public function test_prisma_schema_parser_extracts_models_and_generates_mermaid(): void
    {
        $schema = <<<'PRISMA'
datasource db {
  provider = "postgresql"
  url      = env("DATABASE_URL")
}

model User {
  id        Int       @id @default(autoincrement())
  email     String    @unique
  name      String?
  posts     Post[]
  profile   Profile?
  createdAt DateTime  @default(now())
}

model Profile {
  id     Int    @id @default(autoincrement())
  bio    String
  userId Int    @unique
  user   User   @relation(fields: [userId], references: [id])
}

model Post {
  id        Int      @id @default(autoincrement())
  title     String
  content   String?
  published Boolean  @default(false)
  authorId  Int
  author    User     @relation(fields: [authorId], references: [id])
}
PRISMA;

        $parser = PrismaSchemaParser::parse($schema);

        $this->assertEquals('postgresql', $parser->datasourceProvider);
        $this->assertCount(3, $parser->models);
        $this->assertArrayHasKey('User', $parser->models);
        $this->assertArrayHasKey('Profile', $parser->models);
        $this->assertArrayHasKey('Post', $parser->models);

        $mermaid = $parser->toMermaid();

        $this->assertStringContainsString('erDiagram', $mermaid);
        $this->assertStringContainsString('User {', $mermaid);
        $this->assertStringContainsString('Profile {', $mermaid);
        $this->assertStringContainsString('Post {', $mermaid);
        $this->assertStringContainsString('User ||--o{ Post', $mermaid);
    }

    public function test_project_store_api_accepts_and_saves_database_dialect(): void
    {
        $composerPath = $this->tempDir . DIRECTORY_SEPARATOR . 'composer.json';
        File::put($composerPath, json_encode(['name' => 'test/dialect-proj']));

        $response = $this->postJson('/api/projects', [
            'project_name' => 'Dialect Test Proj',
            'absolute_path' => $this->tempDir,
            'framework_type' => 'laravel',
            'database_dialect' => 'pgsql',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.database_dialect', 'pgsql');

        $this->assertDatabaseHas('projects', [
            'project_name' => 'Dialect Test Proj',
            'database_dialect' => 'pgsql',
        ]);
    }

    public function test_dashboard_and_generator_pages_display_saved_dialect_and_prelock(): void
    {
        $project = Project::create([
            'project_name' => 'Active Prisma Project',
            'absolute_path' => $this->tempDir,
            'framework_type' => TargetFramework::EXPRESS_PRISMA,
            'database_dialect' => DatabaseDialect::POSTGRESQL,
        ]);

        AppSetting::set('active_project_id', $project->id);

        // Test Dashboard
        $dashResponse = $this->get('/dashboard');
        $dashResponse->assertStatus(200);
        $dashResponse->assertSee($project->database_dialect->label());

        // Test Generator
        $genResponse = $this->get('/generator');
        $genResponse->assertStatus(200);
        $genResponse->assertSee('Terkunci Otomatis:');
        $genResponse->assertSee('value="pgsql"', false);
    }

    public function test_detect_database_dialect_returns_null_when_no_evidence_found(): void
    {
        /** @var ProjectService $service */
        $service = app(ProjectService::class);
        $dialect = $service->detectDatabaseDialect($this->tempDir);

        $this->assertNull($dialect, 'Dialect detection should return null when no configuration evidence is found');
    }

    public function test_detect_database_dialect_from_drizzle_and_package_json(): void
    {
        /** @var ProjectService $service */
        $service = app(ProjectService::class);

        // Subtest 1: Drizzle config with postgresql
        File::put($this->tempDir . DIRECTORY_SEPARATOR . 'drizzle.config.ts', <<<'TS'
export default {
    schema: "./src/schema.ts",
    out: "./drizzle",
    dialect: "postgresql",
};
TS
        );
        $this->assertEquals(DatabaseDialect::POSTGRESQL, $service->detectDatabaseDialect($this->tempDir));
        File::delete($this->tempDir . DIRECTORY_SEPARATOR . 'drizzle.config.ts');

        // Subtest 2: package.json with mysql2
        File::put($this->tempDir . DIRECTORY_SEPARATOR . 'package.json', json_encode([
            'dependencies' => ['mysql2' => '^3.0.0']
        ]));
        $this->assertEquals(DatabaseDialect::MYSQL, $service->detectDatabaseDialect($this->tempDir));
    }

    public function test_detect_database_dialect_spring_boot_ignores_h2_and_detects_postgres(): void
    {
        /** @var ProjectService $service */
        $service = app(ProjectService::class);

        $resourcesDir = $this->tempDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'main' . DIRECTORY_SEPARATOR . 'resources';
        File::makeDirectory($resourcesDir, 0755, true);

        // H2 in-memory URL should NOT be mapped to SQLite; should return null
        File::put($resourcesDir . DIRECTORY_SEPARATOR . 'application.properties', "spring.datasource.url=jdbc:h2:mem:testdb\n");
        $this->assertNull($service->detectDatabaseDialect($this->tempDir));

        // PostgreSQL JDBC URL should return POSTGRESQL
        File::put($resourcesDir . DIRECTORY_SEPARATOR . 'application.properties', "spring.datasource.url=jdbc:postgresql://localhost:5432/testdb\n");
        $this->assertEquals(DatabaseDialect::POSTGRESQL, $service->detectDatabaseDialect($this->tempDir));
    }

    public function test_system_and_root_folder_registration_is_rejected(): void
    {
        /** @var ProjectService $service */
        $service = app(ProjectService::class);

        $systemFolder = PHP_OS_FAMILY === 'Windows' ? 'C:\\Windows' : '/etc';

        $validation = $service->validateFolder($systemFolder);
        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('tidak dapat didaftarkan sebagai proyek DEVArchitect', $validation['message']);

        $response = $this->postJson('/api/projects', [
            'project_name' => 'Evil System Project',
            'absolute_path' => $systemFolder,
            'framework_type' => 'raw_sql',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_generate_schema_request_rejects_database_dialect_mismatch_with_422(): void
    {
        $project = Project::create([
            'project_name' => 'Strict PostgreSQL Project',
            'absolute_path' => $this->tempDir,
            'framework_type' => TargetFramework::LARAVEL,
            'database_dialect' => DatabaseDialect::POSTGRESQL,
        ]);

        // Attempting to generate with mysql should trigger validation error 422
        $response = $this->postJson('/api/generations/generate', [
            'project_id' => $project->id,
            'prompt_text' => 'Buatkan skema toko buku lengkap dengan tabel books dan authors.',
            'target_framework' => 'laravel',
            'database_dialect' => 'mysql', // Mismatch with project's pgsql
            'target_version' => '13',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['database_dialect']);
        $this->assertStringContainsString('Dialek telah dikunci sesuai proyek', $response->json('errors.database_dialect.0'));
    }

    public function test_prisma_schema_parser_handles_composite_keys_relations_and_comments(): void
    {
        $schema = <<<'PRISMA'
// Comment with fake provider: provider = "mysql"
/* Multiline comment
   provider = "sqlite"
*/
datasource db {
  provider = "postgresql"
  url      = env("DATABASE_URL")
}

model User {
  id    Int     @id @default(autoincrement())
  email String  @unique
  posts Post[]
}

model Post {
  id       Int    @id @default(autoincrement())
  title    String
  authorId Int
  author   User   @relation("UserPosts", fields: [authorId], references: [id])
}

model OrderItem {
  orderId   Int
  productId Int
  quantity  Int

  @@id([orderId, productId])
  @@unique([orderId, quantity])
}
PRISMA;

        $parser = PrismaSchemaParser::parse($schema);

        // Must extract postgresql despite comments mentioning mysql and sqlite
        $this->assertEquals(DatabaseDialect::POSTGRESQL, $parser->dialect);
        $this->assertEquals('postgresql', $parser->datasourceProvider);

        // Verify composite keys
        $this->assertArrayHasKey('OrderItem', $parser->models);
        $this->assertEquals(['orderId', 'productId'], $parser->models['OrderItem']['primaryKey']);
        $this->assertContains(['orderId', 'quantity'], $parser->models['OrderItem']['uniqueKeys']);

        // Verify Mermaid ERD output
        $mermaid = $parser->toMermaid();
        $this->assertStringContainsString('User ||--o{ Post : "UserPosts"', $mermaid);
        $this->assertStringContainsString('OrderItem {', $mermaid);
        $this->assertStringContainsString('int orderId PK', $mermaid);
        $this->assertStringContainsString('int productId PK', $mermaid);
    }

    public function test_artisan_detect_project_dialects_command(): void
    {
        $envContent = "DB_CONNECTION=pgsql\n";
        File::put($this->tempDir . DIRECTORY_SEPARATOR . '.env', $envContent);

        $project = Project::create([
            'project_name' => 'Legacy Project Without Dialect',
            'absolute_path' => $this->tempDir,
            'framework_type' => TargetFramework::LARAVEL,
            'database_dialect' => null, // Legacy state
        ]);

        // 1. Dry run should detect but not mutate
        $this->artisan('projects:detect-dialects --dry-run')
            ->assertExitCode(0);

        $project->refresh();
        $this->assertNull($project->database_dialect);

        // 2. Apply should persist the detected dialect
        $this->artisan('projects:detect-dialects --apply')
            ->assertExitCode(0);

        $project->refresh();
        $this->assertEquals(DatabaseDialect::POSTGRESQL, $project->database_dialect);
    }
}
