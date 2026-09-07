<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocVersionRequest;
use App\Models\DocProject;
use App\Models\DocVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocVersionController extends Controller
{
    public function index(string $projectId): JsonResponse
    {
        $project = DocProject::findOrFail($projectId);

        return response()->json([
            'success' => true,
            'data' => $project->versions()->orderBy('created_at')->get(),
        ]);
    }

    /**
     * Snapshot draf baru (regenerasi = versi saudara, Q5).
     */
    public function store(StoreDocVersionRequest $request, string $projectId): JsonResponse
    {
        $project = DocProject::findOrFail($projectId);
        $validated = $request->validated();

        $parentId = $validated['parent_version_id'] ?? DocVersion::where('doc_project_id', $project->id)
            ->where('doc_type', $validated['doc_type'])
            ->orderBy('version', 'desc')
            ->value('id');

        $version = DocVersion::create([
            'doc_project_id' => $project->id,
            'doc_type' => $validated['doc_type'],
            'version' => DocVersion::nextVersionNumber($project->id, $validated['doc_type']),
            'content_markdown' => $validated['content_markdown'],
            'status' => 'draft',
            'parent_version_id' => $parentId,
            'ai_model' => $project->ai_model,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Draf {$version->doc_type} v{$version->version} tersimpan.",
            'data' => $version,
        ], 201);
    }

    /**
     * Edit manual teks draf (Q4). Versi approved dikunci.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $version = DocVersion::findOrFail($id);

        if ($version->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya draf yang bisa diedit. Buat snapshot baru untuk revisi.',
            ], 409);
        }

        $validated = $request->validate([
            'content_markdown' => ['required', 'string', 'min:1', 'max:200000'],
        ]);

        $version->update(['content_markdown' => $validated['content_markdown']]);

        return response()->json([
            'success' => true,
            'message' => 'Draf diperbarui.',
            'data' => $version->fresh(),
        ]);
    }

    /**
     * Approve draf + majukan tahap (Q7: berurutan wajib).
     */
    public function approve(string $id): JsonResponse
    {
        $version = DocVersion::with('project')->findOrFail($id);

        if (! $version->project->approveVersion($version)) {
            return response()->json([
                'success' => false,
                'message' => "Tidak dapat approve: tahap proyek [{$version->project->stage}] tidak sesuai tipe [{$version->doc_type}] atau status bukan draft.",
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Disetujui. Tahap lanjut ke [{$version->project->fresh()->stage}].",
            'data' => $version->fresh(),
        ]);
    }

    public function reject(string $id): JsonResponse
    {
        $version = DocVersion::findOrFail($id);

        if ($version->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya draf yang bisa ditolak.',
            ], 409);
        }

        $version->update(['status' => 'rejected']);

        return response()->json(['success' => true, 'message' => 'Draf ditolak.', 'data' => $version->fresh()]);
    }

    /**
     * Handoff 1:1 System Design approved → Generator Skema (Q10).
     * Membuat Generation terhubung (doc_version_id) + dispatch job skema.
     */
    public function toSchema(string $id): JsonResponse
    {
        $version = DocVersion::with('project')->findOrFail($id);

        if (! $version->isApproved() || $version->doc_type !== 'sysdesign') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya System Design yang sudah di-approve yang bisa dikirim ke generator.',
            ], 422);
        }

        $codeProject = $version->project->code_project_id
            ? \App\Models\Project::find($version->project->code_project_id)
            : null;

        if (! $codeProject) {
            return response()->json([
                'success' => false,
                'message' => 'Tautkan proyek kode dulu sebelum handoff.',
            ], 422);
        }

        $dialect = \App\Models\AppSetting::get('default_dialect', 'mysql');
        $prompt = "Berdasarkan SYSTEM DESIGN yang sudah disetujui berikut, rancang skema database lengkap.\n\n"
            . mb_substr($version->content_markdown, 0, 6000);

        $generation = \App\Models\Generation::create([
            'project_id' => $codeProject->id,
            'prompt_text' => $prompt,
            'erd_mermaid_text' => '',
            'migration_files' => [],
            'status' => \App\Enums\GenerationStatus::DRAFT,
            'target_version' => '13',
            'ai_driver' => \App\Models\AppSetting::get('active_ai_driver', 'openrouter'),
            'database_dialect' => $dialect,
            'target_framework' => $codeProject->framework_type,
            'doc_version_id' => $version->id,
            'job_status' => 'queued',
        ]);

        \App\Jobs\GenerateSchemaJob::dispatch($generation->id);

        return response()->json([
            'success' => true,
            'message' => 'Dikirim ke Generator Skema.',
            'data' => $generation,
        ], 202);
    }
}
