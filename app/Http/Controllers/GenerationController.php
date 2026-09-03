<?php

namespace App\Http\Controllers;

use App\Enums\DatabaseDialect;
use App\Enums\GenerationStatus;
use App\Enums\TargetFramework;
use App\Http\Requests\GenerateSchemaRequest;
use App\Http\Requests\UpdateDraftRequest;
use App\Models\Generation;
use App\Models\Project;
use App\Services\Ai\AiManager;
use App\Services\Desktop\DesktopNotificationService;
use App\Services\MigrationInjectorService;
use Illuminate\Http\JsonResponse;
use Throwable;

class GenerationController extends Controller
{
    /**
     * Memproses prompt AI secara asynchronous dan menyimpan draft baru (Mode Dry-Run).
     */
    public function generate(
        GenerateSchemaRequest $request,
        AiManager $aiManager,
        DesktopNotificationService $notificationService
    ): JsonResponse {
        $validated = $request->validated();
        $project = Project::findOrFail($validated['project_id']);

        $framework = TargetFramework::from($validated['target_framework']);
        $dialect = DatabaseDialect::from($validated['database_dialect']);
        $version = $validated['target_version'] ?? '13';

        try {
            // Panggil AI driver (OpenRouter)
            $driver = $aiManager->driver();
            $result = $driver->generate($validated['prompt_text'], $framework, $dialect, $version);

            // Simpan sebagai draft di database (Prinsip Dry-Run)
            $generation = Generation::create([
                'project_id' => $project->id,
                'prompt_text' => $validated['prompt_text'],
                'erd_mermaid_text' => $result['erd_mermaid_text'],
                'migration_files' => $result['migration_files'],
                'status' => GenerationStatus::DRAFT,
                'target_framework' => $framework,
                'database_dialect' => $dialect,
                'target_version' => $version,
                'ai_driver' => 'openrouter',
            ]);

            // Kirim notifikasi native desktop
            $notificationService->notifySchemaGenerated(
                $project->project_name, 
                count($result['migration_files']),
                $framework->label()
            );

            return response()->json([
                'success' => true,
                'message' => 'Rancangan skema berhasil dibuat (Mode Dry-Run).',
                'data' => $generation,
            ], 201);

        } catch (Throwable $e) {
            $notificationService->notifyError('Pembuatan Skema', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mengambil detail draft untuk pratinjau kanvas ERD & code review.
     */
    public function show(string $id): JsonResponse
    {
        $generation = Generation::with('project')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $generation,
        ]);
    }

    /**
     * Menyimpan hasil edit manual pengguna ke dalam draft di database.
     */
    public function update(UpdateDraftRequest $request, string $id): JsonResponse
    {
        $generation = Generation::findOrFail($id);
        $validated = $request->validated();

        $generation->update([
            'erd_mermaid_text' => $validated['erd_mermaid_text'] ?? $generation->erd_mermaid_text,
            'migration_files' => $validated['migration_files'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Perubahan draft berhasil disimpan.',
            'data' => $generation,
        ]);
    }

    /**
     * Menjalankan injeksi file fisik ke direktori proyek lokal.
     */
    public function inject(
        string $id,
        MigrationInjectorService $injectorService,
        DesktopNotificationService $notificationService
    ): JsonResponse {
        $generation = Generation::with('project')->findOrFail($id);
        $project = $generation->project;

        try {
            $injectResult = $injectorService->inject($generation, $project);

            // Notifikasi native Windows
            $notificationService->notifySchemaInjected(
                $project->project_name,
                count($injectResult['written_files']),
                $generation->target_framework->label()
            );

            return response()->json([
                'success' => true,
                'message' => $injectResult['message'],
                'data' => [
                    'written_files' => $injectResult['written_files'],
                    'generation' => $generation->fresh(),
                ],
            ]);

        } catch (Throwable $e) {
            $notificationService->notifyError('Injeksi File', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
