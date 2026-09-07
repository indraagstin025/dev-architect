<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendDocMessageRequest;
use App\Http\Requests\StoreDocProjectRequest;
use App\Jobs\DocChatJob;
use App\Models\DocMessage;
use App\Models\DocProject;
use App\Services\Ai\AiManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DocChatController extends Controller
{
    public function index(): JsonResponse
    {
        $projects = DocProject::orderBy('updated_at', 'desc')->get();

        return response()->json(['success' => true, 'data' => $projects]);
    }

    /**
     * Katalog model untuk picker chatbot (preset + live bila diminta).
     */
    public function modelCatalog(\Illuminate\Http\Request $request, AiManager $aiManager): JsonResponse
    {
        $refresh = $request->boolean('refresh', false);

        return response()->json([
            'success' => true,
            'data' => [
                'presets' => AiManager::docModelPresets(),
                'default' => $aiManager->docsModel(),
                'live' => $aiManager->liveModelCatalog($refresh),
            ],
        ]);
    }

    public function store(StoreDocProjectRequest $request): JsonResponse
    {
        // Stage eksplisit (default DB tidak terbaca di model fresh).
        $project = DocProject::create(array_merge(
            ['stage' => 'brief', 'status' => 'active'],
            $request->validated()
        ));

        return response()->json([
            'success' => true,
            'message' => 'Proyek dokumen dibuat.',
            'data' => $project,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $project = DocProject::with([
            'versions' => fn ($q) => $q->orderBy('created_at'),
            'codeProject',
        ])->findOrFail($id);

        // TASK-M2-06: Hanya ambil 25 pesan aktif terbaru (diurutkan kronologis)
        $messagesQuery = $project->messages()->where('is_archived', false);
        $totalActive = $messagesQuery->count();

        $messages = (clone $messagesQuery)
            ->reorder('created_at', 'desc')
            ->limit(25)
            ->get()
            ->reverse()
            ->values();

        $projectData = $project->toArray();
        $projectData['messages'] = $messages;
        $projectData['has_more_messages'] = $totalActive > 25;
        $projectData['total_messages'] = $totalActive;
        $projectData['messages_pagination'] = [
            'has_more' => $totalActive > 25,
            'oldest_id' => $messages->first()?->id,
            'total' => $totalActive,
        ];

        return response()->json(['success' => true, 'data' => $projectData]);
    }

    /**
     * Mengambil riwayat pesan obrolan dengan cursor pagination (TASK-M2-06).
     */
    public function getMessages(\Illuminate\Http\Request $request, string $id): JsonResponse
    {
        $project = DocProject::findOrFail($id);
        $limit = min((int) $request->input('limit', 20), 50);
        $beforeId = $request->input('before_id');

        $query = $project->messages()->where('is_archived', false);

        if ($beforeId) {
            $reference = DocMessage::find($beforeId);
            if ($reference) {
                $query->where('created_at', '<', $reference->created_at);
            }
        }

        $messages = $query->reorder('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $oldestMessage = $messages->first();
        $hasMore = false;
        if ($oldestMessage) {
            $hasMore = $project->messages()
                ->where('is_archived', false)
                ->where('created_at', '<', $oldestMessage->created_at)
                ->exists();
        }

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => $messages,
            'has_more' => $hasMore,
            'oldest_id' => $oldestMessage?->id,
            'count' => $messages->count(),
        ]);
    }

    /**
     * Menerbitkan kartu proyek tahap awal (Draft Ide 1/4) di Dashboard (TASK-M2-01 & TASK-M2-02).
     */
    public function createDashboardProject(string $id): JsonResponse
    {
        $docProject = DocProject::findOrFail($id);

        if ($docProject->code_project_id) {
            $existing = \App\Models\Project::find($docProject->code_project_id);
            if ($existing) {
                \App\Models\AppSetting::set('active_project_id', $existing->id);

                return response()->json([
                    'status' => 'success',
                    'success' => true,
                    'message' => "Proyek [{$existing->project_name}] sudah terdaftar di Dashboard.",
                    'data' => $existing,
                ], 200);
            }
        }

        $framework = $docProject->target_framework ?: 'laravel';
        if (! in_array($framework, ['laravel', 'express_prisma', 'express_drizzle', 'springboot_hibernate', 'raw_sql'])) {
            $framework = 'laravel';
        }

        $name = \Illuminate\Support\Str::slug($docProject->title);
        if (empty($name)) {
            $name = 'proyek-' . substr($docProject->id, 0, 8);
        }

        // Hindari duplikasi nama proyek
        $baseName = $name;
        $counter = 1;
        while (\App\Models\Project::where('project_name', $name)->exists()) {
            $name = $baseName . '-' . $counter;
            $counter++;
        }

        $project = \App\Models\Project::create([
            'project_name' => $name,
            'absolute_path' => null, // Draft virtual tanpa folder fisik
            'framework_type' => $framework,
            'is_draft' => true,
            'doc_project_id' => $docProject->id,
        ]);

        $docProject->update(['code_project_id' => $project->id]);
        \App\Models\AppSetting::set('active_project_id', $project->id);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => "Konsep '{$docProject->title}' berhasil diterbitkan sebagai Proyek di Dashboard!",
            'data' => $project,
        ], 201);
    }

    /**
     * Mengarsipkan obrolan saat ini untuk memulai topik baru (TASK-M2-08).
     */
    public function archiveChat(string $id): JsonResponse
    {
        $docProject = DocProject::findOrFail($id);
        $count = $docProject->archiveCurrentChat();

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => "Sesi obrolan berhasil diarsipkan ({$count} pesan). Anda dapat memulai topik diskusi baru tanpa kehilangan draf Canvas.",
            'data' => [
                'archived_count' => $count,
                'doc_project' => $docProject->fresh(),
            ],
        ]);
    }

    /**
     * Mengunduh transkrip diskusi dalam format Markdown (TASK-M2-09).
     */
    public function exportTranscript(string $id): \Symfony\Component\HttpFoundation\Response
    {
        $docProject = DocProject::findOrFail($id);
        $markdown = $docProject->getTranscriptMarkdown();
        $filename = \Illuminate\Support\Str::slug($docProject->title) . '-transcript-' . date('Ymd-His') . '.md';

        if (request()->wantsJson() || request()->header('Accept') === 'application/json' || request()->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'success' => true,
                'data' => [
                    'filename' => $filename,
                    'transcript_md' => $markdown,
                ],
            ]);
        }

        return response($markdown, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function update(\Illuminate\Http\Request $request, string $id): JsonResponse
    {
        $project = DocProject::findOrFail($id);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'min:3', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'target_framework' => ['sometimes', 'nullable', 'string', 'max:40'],
            'ai_model' => ['sometimes', 'nullable', 'string', 'max:100'],
            'code_project_id' => ['sometimes', 'nullable', 'uuid', 'exists:projects,id'],
        ]);

        $project->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Proyek dokumen diperbarui.',
            'data' => $project->fresh(),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        if (! \Illuminate\Support\Str::isUuid($id)) {
            return response()->json(['success' => false, 'message' => 'Format ID tidak valid.'], 400);
        }

        $project = DocProject::find($id);

        if (! $project) {
            return response()->json(['success' => false, 'message' => 'Proyek dokumen tidak ditemukan.'], 404);
        }

        $project->delete();

        return response()->json(['success' => true, 'message' => 'Proyek dokumen dihapus permanen.']);
    }

    public function archive(string $id): JsonResponse
    {
        $project = DocProject::findOrFail($id);
        $project->update(['status' => $project->status === 'archived' ? 'active' : 'archived']);

        return response()->json([
            'success' => true,
            'message' => $project->status === 'archived' ? 'Proyek diarsipkan.' : 'Arsip dibuka kembali.',
            'data' => $project->fresh(),
        ]);
    }

    /**
     * Kirim pesan user + antrekan balasan assistant (202).
     */
    public function sendMessage(SendDocMessageRequest $request, string $id): JsonResponse
    {
        $project = DocProject::findOrFail($id);

        if ($project->status === 'archived') {
            return response()->json([
                'success' => false,
                'message' => 'Proyek diarsipkan. Buka arsip dulu untuk melanjutkan diskusi.',
            ], 409);
        }

        $userMessage = DocMessage::create([
            'doc_project_id' => $project->id,
            'role' => 'user',
            'content' => $request->validated()['content'],
            'stage' => $project->stage,
            'job_status' => 'ready',
        ]);

        $assistantMessage = DocMessage::create([
            'doc_project_id' => $project->id,
            'role' => 'assistant',
            'content' => '',
            'stage' => $project->stage,
            'ai_model' => $project->ai_model,
            'job_status' => 'queued',
        ]);

        DocChatJob::dispatch($assistantMessage->id);

        return response()->json([
            'success' => true,
            'message' => 'Pesan masuk antrean.',
            'data' => [
                'user_message' => $userMessage,
                'assistant_message' => $assistantMessage,
            ],
        ], 202);
    }

    public function messageStatus(string $messageId): JsonResponse
    {
        $message = DocMessage::findOrFail($messageId);

        return response()->json(['success' => true, 'data' => $message]);
    }

    /**
     * Generate satu dokumen (atau satu seksi, Q2) via builder + chaining.
     */
    public function generateDoc(\Illuminate\Http\Request $request, string $id): JsonResponse
    {
        $project = DocProject::findOrFail($id);

        if ($project->status === 'archived') {
            return response()->json([
                'success' => false,
                'message' => 'Proyek diarsipkan. Buka arsip dulu untuk melanjutkan.',
            ], 409);
        }

        $validated = $request->validate([
            'doc_type' => ['required', 'string', \Illuminate\Validation\Rule::in(\App\Services\Ai\Prompts\Docs\DocPromptFactory::types())],
            'section' => ['nullable', 'string', 'max:120'],
        ]);

        $builder = \App\Services\Ai\Prompts\Docs\DocPromptFactory::for($validated['doc_type']);

        if (! empty($validated['section']) && ! in_array($validated['section'], $builder::sections(), true)) {
            return response()->json([
                'success' => false,
                'message' => 'Seksi tidak dikenal untuk tipe dokumen ini.',
                'sections' => $builder::sections(),
            ], 422);
        }

        $assistantMessage = DocMessage::create([
            'doc_project_id' => $project->id,
            'role' => 'assistant',
            'content' => '',
            'stage' => $project->stage,
            'ai_model' => $project->ai_model,
            'job_status' => 'queued',
        ]);

        \App\Jobs\DocGenerateJob::dispatch(
            $assistantMessage->id,
            $validated['doc_type'],
            $validated['section'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Penyusunan dokumen masuk antrean.',
            'data' => $assistantMessage,
        ], 202);
    }

    /**
     * Daftar seksi per tipe dokumen (untuk dropdown UI).
     */
    public function docSections(): JsonResponse
    {
        $map = [];
        foreach (\App\Services\Ai\Prompts\Docs\DocPromptFactory::types() as $type) {
            $builder = \App\Services\Ai\Prompts\Docs\DocPromptFactory::for($type);
            $map[$type] = $builder::sections();
        }

        return response()->json(['success' => true, 'data' => $map]);
    }

    public function cancelMessage(string $messageId): JsonResponse
    {        $message = DocMessage::findOrFail($messageId);

        if (! $message->isPending()) {
            return response()->json([
                'success' => false,
                'message' => "Tidak dapat dibatalkan (status: {$message->job_status}).",
            ], 409);
        }

        $message->update(['job_status' => 'cancelled']);

        try {
            DB::table('jobs')->where('payload', 'like', '%' . $message->id . '%')->delete();
        } catch (Throwable $e) {
            Log::warning('Gagal menghapus job database saat cancel pesan: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Balasan assistant dibatalkan.',
            'data' => $message->fresh(),
        ]);
    }
}
