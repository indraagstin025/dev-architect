<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScaffoldProjectRequest;
use App\Jobs\ScaffoldProjectJob;
use App\Models\ScaffoldJob;
use App\Services\ScaffoldProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ScaffoldController extends Controller
{
    /**
     * Ketersediaan tool + direktori parent default (TASK-606).
     */
    public function prerequisites(ScaffoldProjectService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $service->prerequisites(),
        ]);
    }

    /**
     * Antrekan pembuatan proyek baru (202). Gagal cepat bila prasyarat tak terpenuhi.
     */
    public function store(
        ScaffoldProjectRequest $request,
        ScaffoldProjectService $service
    ): JsonResponse {
        $validated = $request->validated();

        try {
            // Fail-fast: validasi target + susun rencana sebelum masuk antrean.
            $plan = $service->plan(
                $validated['template'],
                $validated['project_name'],
                $validated['parent_path'],
                [
                    'spring_group' => $validated['spring_group'] ?? 'com.example',
                    'spring_artifact' => $validated['spring_artifact'] ?? $validated['project_name'],
                ]
            );

            $missing = $this->missingTools($validated['template'], $service);
            if ($missing !== []) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tool belum tersedia: ' . implode(', ', $missing) . '.',
                ], 422);
            }
        } catch (Throwable $e) {
            Log::warning('Scaffold ditolak saat validasi: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $scaffoldJob = ScaffoldJob::create([
            'template' => $validated['template'],
            'project_name' => $validated['project_name'],
            'parent_path' => $plan['parent'],
            'target_path' => $plan['target'],
            'options' => [
                'spring_group' => $validated['spring_group'] ?? 'com.example',
                'spring_artifact' => $validated['spring_artifact'] ?? $validated['project_name'],
            ],
            'status' => 'queued',
        ]);

        // Luncurkan jendela native console (PowerShell) jika di desktop Windows
        $externalLaunched = false;
        if (! app()->runningUnitTests()) {
            $externalLaunched = ScaffoldProjectService::launchExternalConsole($scaffoldJob);
        }

        // Fallback ke background queue jika di unit test atau bukan Windows
        if (! $externalLaunched) {
            ScaffoldProjectJob::dispatch($scaffoldJob->id);
        }

        return response()->json([
            'success' => true,
            'message' => $externalLaunched
                ? 'Jendela PowerShell dibuka untuk proses pembuatan proyek.'
                : 'Pembuatan proyek masuk antrean.',
            'external_console' => $externalLaunched,
            'data' => $scaffoldJob,
        ], 202);
    }

    public function status(string $id): JsonResponse
    {
        $job = ScaffoldJob::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $job,
        ]);
    }

    public function cancel(string $id, ScaffoldProjectService $service): JsonResponse
    {
        $job = ScaffoldJob::findOrFail($id);

        if (! in_array($job->status, ['queued', 'processing'], true)) {
            return response()->json([
                'success' => false,
                'message' => "Tidak dapat dibatalkan (status: {$job->status}).",
            ], 409);
        }

        $job->update(['status' => 'cancelled']);
        $job->appendLog('Dibatalkan pengguna.');

        if (! empty($job->target_path)) {
            $service->cleanupTarget($job->target_path);
        }

        try {
            DB::table('jobs')->where('payload', 'like', '%' . $job->id . '%')->delete();
        } catch (Throwable $e) {
            Log::warning('Gagal menghapus job database saat cancel scaffold: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Pembuatan proyek dibatalkan.',
            'data' => $job->fresh(),
        ]);
    }

    /**
     * @return array<string> nama tool yang hilang untuk template tersebut
     */
    protected function missingTools(string $template, ScaffoldProjectService $service): array
    {
        $pre = $service->prerequisites();
        $need = match ($template) {
            'laravel' => ['php', 'composer'],
            'express_prisma', 'express_drizzle' => ['node', 'npm'],
            'springboot_hibernate' => ['java', 'zip', 'internet'],
            default => [],
        };

        $missing = [];
        foreach ($need as $tool) {
            if (empty($pre[$tool]['ok'])) {
                $missing[] = $tool;
            }
        }

        return $missing;
    }
}
