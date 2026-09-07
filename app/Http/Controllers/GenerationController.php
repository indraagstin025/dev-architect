<?php

namespace App\Http\Controllers;

use App\Enums\DatabaseDialect;
use App\Enums\GenerationStatus;
use App\Enums\TargetFramework;
use App\Http\Requests\GenerateSchemaRequest;
use App\Http\Requests\UpdateDraftRequest;
use App\Jobs\GenerateSchemaJob;
use App\Models\AppSetting;
use App\Models\Generation;
use App\Models\Project;
use App\Services\Ai\AiManager;
use App\Services\Desktop\DesktopNotificationService;
use App\Services\MigrationInjectorService;
use App\Services\MigrationLinterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class GenerationController extends Controller
{
    /**
     * Menerima prompt AI dan memasukkannya ke antrean (async).
     * Frontend melakukan polling ke status() hingga job_status = ready/failed.
     */
    public function generate(GenerateSchemaRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $project = Project::findOrFail($validated['project_id']);

        $framework = TargetFramework::from($validated['target_framework']);
        $dialect = $project->database_dialect ?? DatabaseDialect::from($validated['database_dialect']);
        $version = $validated['target_version'] ?? '13';
        $driverName = AppSetting::get('active_ai_driver', 'openrouter');

        $generation = Generation::create([
            'project_id' => $project->id,
            'prompt_text' => $validated['prompt_text'],
            'erd_mermaid_text' => '',
            'migration_files' => [],
            'status' => GenerationStatus::DRAFT,
            'target_framework' => $framework,
            'database_dialect' => $dialect,
            'target_version' => $version,
            'ai_driver' => $driverName,
            'job_status' => 'queued',
        ]);

        GenerateSchemaJob::dispatch($generation->id);

        return response()->json([
            'success' => true,
            'message' => 'Permintaan masuk antrean. AI sedang merancang skema...',
            'data' => $generation,
        ], 202);
    }

    /**
     * Status ringan untuk polling frontend (tanpa memuat kolom file berat).
     */
    public function status(string $id): JsonResponse
    {
        $generation = Generation::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $generation->id,
                'job_status' => $generation->job_status,
                'job_error' => $generation->job_error,
                'job_warnings' => $generation->job_warnings ?? [],
                'status' => $generation->status,
            ],
        ]);
    }

    /**
     * Membatalkan job yang masih queued/processing.
     */
    public function cancel(string $id): JsonResponse
    {
        $generation = Generation::findOrFail($id);

        if (! in_array($generation->job_status, ['queued', 'processing'], true)) {
            return response()->json([
                'success' => false,
                'message' => "Rancangan tidak dapat dibatalkan (status antrean: {$generation->job_status}).",
            ], 409);
        }

        $generation->update(['job_status' => 'cancelled']);

        // Hapus baris job database yang belum jalan (best-effort).
        try {
            DB::table('jobs')->where('payload', 'like', '%' . $generation->id . '%')->delete();
        } catch (Throwable $e) {
            Log::warning('Gagal menghapus job database saat cancel: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Permintaan pembuatan skema dibatalkan.',
            'data' => $generation->fresh(),
        ]);
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
    public function update(
        UpdateDraftRequest $request, 
        string $id, 
        MigrationLinterService $linter
    ): JsonResponse {
        $generation = Generation::with('project')->findOrFail($id);

        // Guard: Tolak pengeditan jika skema sudah diinjeksi ke file fisik
        if ($generation->status === GenerationStatus::INJECTED) {
            return response()->json([
                'success' => false,
                'message' => 'Rancangan yang sudah diinjeksi ke proyek tidak dapat diubah langsung. Silakan buat rancangan baru atau lakukan revisi.',
            ], 409);
        }

        $validated = $request->validated();

        // Validasi kode yang diedit manual dengan linter
        $linterResult = $linter->validateDraft(
            $validated['migration_files'], 
            $generation->project, 
            $generation->target_framework, 
            $generation->database_dialect
        );

        if (!$linterResult['isValid']) {
            return response()->json([
                'success' => false,
                'message' => 'Perubahan kode tidak valid.',
                'errors' => $linterResult['errors'],
                'warnings' => $linterResult['warnings'],
            ], 422);
        }

        $generation->update([
            'erd_mermaid_text' => $validated['erd_mermaid_text'] ?? $generation->erd_mermaid_text,
            'migration_files' => $validated['migration_files'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Perubahan draft berhasil disimpan.',
            'data' => $generation,
            'warnings' => $linterResult['warnings'],
        ]);
    }

    /**
     * Riwayat generasi per proyek, ringan tanpa kolom file berat (TASK-807).
     * Mendukung ?sort= & ?direction= dari whitelist agar aman dari SQL injection.
     */
    public function history(\Illuminate\Http\Request $request, string $projectId): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        $validated = $request->validate([
            'sort' => ['sometimes', 'string', Rule::in(['created_at', 'updated_at', 'status', 'job_status', 'target_framework'])],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
        ]);

        $generations = Generation::where('project_id', $project->id)
            ->select([
                'id', 'project_id', 'prompt_text', 'status', 'job_status',
                'job_error', 'target_version', 'ai_driver', 'database_dialect',
                'target_framework', 'created_at', 'updated_at',
            ])
            ->orderBy($validated['sort'] ?? 'created_at', $validated['direction'] ?? 'desc')
            ->paginate(15)
            ->appends($request->only(['sort', 'direction']));

        return response()->json([
            'success' => true,
            'data' => $generations,
        ]);
    }

    /**
     * Pratinjau injeksi untuk modal konfirmasi (TASK-802).
     */
    public function conflicts(
        string $id,
        MigrationInjectorService $injectorService
    ): JsonResponse {
        $generation = Generation::with('project')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $injectorService->preview($generation, $generation->project),
        ]);
    }

    /**
     * Menjalankan injeksi file fisik ke direktori proyek lokal.
     */
    public function inject(
        \Illuminate\Http\Request $request,
        string $id,
        MigrationInjectorService $injectorService,
        DesktopNotificationService $notificationService
    ): JsonResponse {
        $generation = Generation::with('project')->findOrFail($id);
        $project = $generation->project;

        $validatedQuery = $request->validate([
            'force' => ['sometimes', 'boolean'],
        ]);
        $force = (bool) ($validatedQuery['force'] ?? false);

        // Guard: Cegah re-injeksi tidak sengaja
        if ($generation->status === GenerationStatus::INJECTED && !$force) {
            return response()->json([
                'success' => false,
                'message' => 'Rancangan ini sudah pernah disuntikkan ke proyek lokal. Berikan parameter force=1 jika ingin menyuntikkan ulang.',
            ], 409);
        }

        try {
            $injectResult = $injectorService->inject($generation, $project, $force);

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
            \Illuminate\Support\Facades\Log::error('Gagal injeksi skema ke direktori lokal: ' . $e->getMessage(), [
                'project_id' => $project->id ?? null,
                'generation_id' => $generation->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $notificationService->notifyError('Injeksi Berkas', 'Gagal menyuntikkan berkas skema.');

            $message = (app()->hasDebugModeEnabled() && config('app.debug'))
                ? $e->getMessage()
                : ($e instanceof \RuntimeException ? $e->getMessage() : 'Terjadi kesalahan sistem saat menulis berkas ke disk.');

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }
    }
}
