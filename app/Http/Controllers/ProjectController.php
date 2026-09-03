<?php

namespace App\Http\Controllers;

use App\Enums\TargetFramework;
use App\Http\Requests\StoreProjectRequest;
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
    public function browse(DesktopDialogService $dialogService): JsonResponse
    {
        $result = $dialogService->pickProjectFolder();

        return response()->json($result);
    }

    /**
     * Menyimpan/mendaftarkan proyek baru.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $project = $this->projectService->registerProject(
            name: $validated['project_name'],
            absolutePath: $validated['absolute_path'],
            framework: isset($validated['framework_type']) ? TargetFramework::from($validated['framework_type']) : null
        );

        return response()->json([
            'success' => true,
            'message' => 'Proyek berhasil didaftarkan.',
            'data' => $project,
        ], 201);
    }

    /**
     * Menghapus proyek dari dashboard.
     */
    public function destroy(string $id): JsonResponse
    {
        $deleted = $this->projectService->deleteProject($id);

        return response()->json([
            'success' => $deleted,
            'message' => $deleted ? 'Proyek berhasil dihapus.' : 'Proyek tidak ditemukan.',
        ]);
    }
}
