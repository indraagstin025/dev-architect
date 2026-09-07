<?php

namespace App\Http\Controllers;

use App\Enums\TargetFramework;
use App\Http\Requests\StoreProjectRequest;
use App\Models\AppSetting;
use App\Models\Project;
use App\Services\Desktop\DesktopDialogService;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;

class ProjectController extends Controller
{
    public function __construct(
        protected ProjectService $projectService
    ) {}

    /**
     * Mengambil daftar seluruh proyek yang tersimpan.
     */
    public function index(): JsonResponse
    {
        $projects = $this->projectService->listProjects();

        return response()->json([
            'success' => true,
            'data' => $projects,
        ]);
    }

    /**
     * Membuka dialog native Windows Explorer untuk memilih folder proyek.
     */
    public function browse(\Illuminate\Http\Request $request, DesktopDialogService $dialogService): JsonResponse
    {
        $path = $request->input('path');
        if ($path) {
            $validation = $this->projectService->validateFolder($path);
            return response()->json([
                'cancelled' => false,
                'path' => $path,
                'valid' => $validation['valid'],
                'message' => $validation['message'],
                'project_name' => $validation['project_name'] ?? null,
                'framework' => $validation['framework'] ?? null,
                'framework_label' => $validation['framework_label'] ?? null,
                'dialect' => $validation['dialect'] ?? null,
                'dialect_label' => $validation['dialect_label'] ?? null,
                'is_generic_folder' => $validation['is_generic_folder'] ?? false,
                'requires_confirmation' => $validation['requires_confirmation'] ?? false,
            ]);
        }

        $result = $dialogService->pickProjectFolder();

        return response()->json($result);
    }

    /**
     * Menyimpan/mendaftarkan proyek baru.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $project = $this->projectService->registerProject(
                name: $validated['project_name'],
                absolutePath: $validated['absolute_path'],
                framework: isset($validated['framework_type']) ? TargetFramework::from($validated['framework_type']) : null,
                dialect: isset($validated['database_dialect']) ? \App\Enums\DatabaseDialect::from($validated['database_dialect']) : null,
                confirmGenericFolder: (bool) ($validated['confirm_generic_folder'] ?? false)
            );

            return response()->json([
                'success' => true,
                'message' => 'Proyek berhasil didaftarkan.',
                'data' => $project,
            ], 201);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Gagal mendaftarkan proyek: ' . $e->getMessage(), [
                'absolute_path' => $validated['absolute_path'] ?? null,
                'error' => $e->getMessage(),
            ]);

            $status = str_contains($e->getMessage(), 'sudah terdaftar') ? 409 : 422;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    /**
     * Menghapus proyek dari dashboard.
     */
    public function destroy(string $id): JsonResponse
    {
        if (!\Illuminate\Support\Str::isUuid($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Format ID proyek tidak valid.',
            ], 400);
        }

        $deleted = $this->projectService->deleteProject($id);

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Proyek tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Proyek berhasil dihapus.',
        ]);
    }

    /**
     * Mengambil data proyek yang sedang aktif (TASK-605).
     */
    public function getActive(): JsonResponse
    {
        $activeId = AppSetting::get('active_project_id');
        $project = $activeId ? Project::find($activeId) : Project::latest()->first();

        return response()->json([
            'success' => true,
            'data' => $project,
        ]);
    }

    /**
     * Menetapkan proyek yang sedang aktif (TASK-605).
     */
    public function setActive(\Illuminate\Http\Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'uuid', 'exists:projects,id'],
        ]);

        AppSetting::set('active_project_id', $validated['project_id']);
        $project = Project::find($validated['project_id']);

        return response()->json([
            'success' => true,
            'message' => "Proyek [{$project->project_name}] ditetapkan sebagai proyek aktif.",
            'data' => $project,
        ]);
    }

    /**
     * Membuka direktori proyek di editor atau file explorer (TASK-604).
     */
    public function openInEditor(\App\Http\Requests\OpenProjectInEditorRequest $request, string $id): JsonResponse
    {
        $project = Project::findOrFail($id);
        $target = $request->input('target');
        $path = $project->absolute_path;

        if (!\Illuminate\Support\Facades\File::isDirectory($path)) {
            return response()->json([
                'success' => false,
                'message' => "Direktori proyek tidak ditemukan di disk: {$path}",
            ], 404);
        }

        // Mapping perintah eksekusi per target editor di Windows.
        // Catatan: escapeshellarg() menghasilkan single-quote yang tidak
        // dikenali cmd.exe, jadi path diapit double-quote secara eksplisit
        // agar direktori berekstensi spasi tetap terbuka dengan benar.
        $hasWt = false;
        @exec('where.exe wt.exe 2>NUL', $wtOut, $wtCode);
        if ($wtCode === 0 && !empty($wtOut)) {
            $hasWt = true;
        }

        $quoted = '"' . $path . '"';
        $editorConfigs = [
            'explorer' => [
                'name' => 'File Explorer',
                'cmd' => 'explorer ' . $quoted,
            ],
            'vscode' => [
                'name' => 'Visual Studio Code',
                'cmd' => 'code ' . $quoted,
            ],
            'zed' => [
                'name' => 'Zed Editor',
                'cmd' => 'zed ' . $quoted,
            ],
            'antigravity' => [
                'name' => 'Antigravity IDE',
                'cmd' => 'agy ' . $quoted,
            ],
            'terminal' => [
                'name' => 'Terminal',
                'cmd' => $hasWt ? 'wt.exe -d ' . $quoted : 'powershell.exe -NoExit -Command "Set-Location ' . $quoted . '"',
            ],
        ];

        $targetConfig = $editorConfigs[$target] ?? null;
        if (!$targetConfig) {
            return response()->json([
                'success' => false,
                'message' => "Target editor [{$target}] tidak didukung.",
            ], 422);
        }

        try {
            // Jangan meluncurkan proses nyata saat automated testing
            if (app()->runningUnitTests()) {
                return response()->json([
                    'success' => true,
                    'message' => "Membuka proyek di {$targetConfig['name']}...",
                ]);
            }

            // Eksekusi non-blocking di Windows
            if ($target === 'terminal') {
                pclose(popen("start {$targetConfig['cmd']}", 'r'));
            } else {
                pclose(popen("start /B {$targetConfig['cmd']}", 'r'));
            }

            return response()->json([
                'success' => true,
                'message' => "Membuka proyek di {$targetConfig['name']}...",
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal membuka proyek di editor [{$target}]: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => "Gagal meluncurkan {$targetConfig['name']}. Pastikan binary terpasang di sistem.",
            ], 500);
        }
    }
}
