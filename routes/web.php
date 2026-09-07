<?php

use App\Http\Controllers\DocChatController;
use App\Http\Controllers\DocVersionController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ScaffoldController;
use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

// 1. Tampilan Antarmuka Desktop (Blade Views)
Route::get('/', function () {
    return view('assistant');
});

Route::get('/assistant', function () {
    return view('assistant');
});

Route::get('/dashboard', function () {
    return view('dashboard');
});

Route::get('/generator', function () {
    $activeProjectId = \App\Models\AppSetting::get('active_project_id');
    $activeProject = $activeProjectId
        ? \App\Models\Project::find($activeProjectId)
        : \App\Models\Project::orderBy('updated_at', 'desc')->first();

    $defaultFramework = \App\Models\AppSetting::get('default_framework', 'laravel');
    $defaultDialect = \App\Models\AppSetting::get('default_dialect', 'mysql');

    $projectFramework = $activeProject?->framework_type?->value ?? $defaultFramework;
    $projectDialect = $activeProject?->database_dialect?->value ?? $defaultDialect;
    $isDialectLocked = $activeProject?->database_dialect !== null;

    $allowedFrameworks = $activeProject
        ? app(\App\Services\ProjectService::class)->allowedTargetFrameworks($activeProject)
        : [];

    return view('generator', [
        'activeProject' => $activeProject,
        'projectFramework' => $projectFramework,
        'projectDialect' => $projectDialect,
        'isDialectLocked' => $isDialectLocked,
        'allowedFrameworks' => $allowedFrameworks,
    ]);
});

Route::get('/generations/{id}', function (string $id) {
    return view('generations.show', ['generationId' => $id]);
});

Route::get('/history', function () {
    return view('history');
});

Route::get('/settings', function () {
    return view('settings');
});

// 2. API Endpoints untuk Desktop Frontend DEVArchitect
Route::prefix('api')->middleware([
    \App\Http\Middleware\EnforceDesktopLoopbackAccess::class,
    'throttle:300,1',
])->group(function () {
    // Modul Proyek
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/active', [ProjectController::class, 'getActive']);
    Route::post('/projects/active', [ProjectController::class, 'setActive']);
    Route::post('/projects/browse', [ProjectController::class, 'browse']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::post('/projects/{id}/open', [ProjectController::class, 'openInEditor']);
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);
    Route::get('/projects/{id}/generations', [GenerationController::class, 'history']);

    // Modul AI Generator & Dry-Run
    Route::post('/generations/generate', [GenerationController::class, 'generate'])->middleware('throttle:30,1');
    Route::get('/generations/{id}/status', [GenerationController::class, 'status'])->withoutMiddleware('throttle:300,1');
    Route::post('/generations/{id}/cancel', [GenerationController::class, 'cancel']);
    Route::get('/generations/{id}/conflicts', [GenerationController::class, 'conflicts']);
    Route::get('/generations/{id}', [GenerationController::class, 'show']);
    Route::put('/generations/{id}', [GenerationController::class, 'update']);
    Route::post('/generations/{id}/inject', [GenerationController::class, 'inject'])->middleware('throttle:30,1');

    // Modul Scaffold Proyek Baru (TASK-606)
    Route::get('/scaffold/prerequisites', [ScaffoldController::class, 'prerequisites']);
    Route::post('/projects/scaffold', [ScaffoldController::class, 'store'])->middleware('throttle:30,1');
    Route::get('/scaffold/{id}/status', [ScaffoldController::class, 'status'])->withoutMiddleware('throttle:300,1');
    Route::post('/scaffold/{id}/cancel', [ScaffoldController::class, 'cancel']);

    // Modul Chatbot Dokumen (TASK-1002)
    Route::get('/docs/projects', [DocChatController::class, 'index']);
    Route::get('/docs/models', [DocChatController::class, 'modelCatalog']);
    Route::get('/docs/sections', [DocChatController::class, 'docSections']);
    Route::post('/docs/projects/{id}/generate-doc', [DocChatController::class, 'generateDoc'])->middleware('throttle:30,1');
    Route::post('/docs/projects', [DocChatController::class, 'store']);
    Route::get('/docs/projects/{id}', [DocChatController::class, 'show']);
    Route::put('/docs/projects/{id}', [DocChatController::class, 'update']);
    Route::delete('/docs/projects/{id}', [DocChatController::class, 'destroy']);
    Route::post('/docs/projects/{id}/archive', [DocChatController::class, 'archive']);
    Route::post('/docs/projects/{id}/messages', [DocChatController::class, 'sendMessage'])->middleware('throttle:30,1');
    Route::get('/docs/messages/{messageId}/status', [DocChatController::class, 'messageStatus'])->withoutMiddleware('throttle:300,1');
    Route::post('/docs/messages/{messageId}/cancel', [DocChatController::class, 'cancelMessage']);
    Route::get('/docs/projects/{projectId}/versions', [DocVersionController::class, 'index']);
    Route::post('/docs/projects/{projectId}/versions', [DocVersionController::class, 'store']);
    Route::put('/docs/versions/{id}', [DocVersionController::class, 'update']);
    Route::post('/docs/versions/{id}/approve', [DocVersionController::class, 'approve']);
    Route::post('/docs/versions/{id}/reject', [DocVersionController::class, 'reject']);
    Route::post('/docs/versions/{id}/to-schema', [DocVersionController::class, 'toSchema']);

    // Modul Pengaturan
    Route::get('/settings', [SettingController::class, 'index']);
    Route::post('/settings', [SettingController::class, 'update']);

    // Modul Kontrol Jendela Desktop
    Route::post('/window/minimize', function (\Illuminate\Http\Request $request) {
        $id = $request->input('id', 'main');
        if (class_exists(\Native\Desktop\Facades\Window::class)) {
            try {
                \Native\Desktop\Facades\Window::minimize($id);
            } catch (\Throwable $e) {}
        }
        return response()->json(['success' => true, 'action' => 'minimize']);
    });

    Route::post('/window/maximize', function (\Illuminate\Http\Request $request) {
        $id = $request->input('id', 'main');
        if (class_exists(\Native\Desktop\Client\Client::class)) {
            try {
                $client = app(\Native\Desktop\Client\Client::class);
                $res = $client->post('window/maximize', ['id' => $id]);
                if ($res->successful()) {
                    return response()->json($res->json());
                }
            } catch (\Throwable $e) {}
        }
        if (class_exists(\Native\Desktop\Facades\Window::class)) {
            try {
                \Native\Desktop\Facades\Window::maximize($id);
            } catch (\Throwable $e) {}
        }
        return response()->json(['success' => true, 'action' => 'maximize']);
    });

    Route::post('/window/close', function (\Illuminate\Http\Request $request) {
        $id = $request->input('id', 'main');
        if (class_exists(\Native\Desktop\Facades\Window::class)) {
            try {
                \Native\Desktop\Facades\Window::close($id);
            } catch (\Throwable $e) {}
        }
        return response()->json(['success' => true, 'action' => 'close']);
    });

    Route::post('/window/reload', function (\Illuminate\Http\Request $request) {
        $id = $request->input('id', 'main');
        if (class_exists(\Native\Desktop\Facades\Window::class)) {
            try {
                \Native\Desktop\Facades\Window::reload($id);
            } catch (\Throwable $e) {}
        }
        return response()->json(['success' => true, 'action' => 'reload']);
    });

    Route::post('/window/snap', function (\Illuminate\Http\Request $request) {
        $id = $request->input('id', 'main');
        $mode = $request->input('mode', 'compact');
        if (class_exists(\Native\Desktop\Client\Client::class)) {
            try {
                $client = app(\Native\Desktop\Client\Client::class);
                $res = $client->post('window/snap', ['id' => $id, 'mode' => $mode]);
                if ($res->successful()) {
                    return response()->json($res->json());
                }
            } catch (\Throwable $e) {}
        }
        if (class_exists(\Native\Desktop\Facades\Window::class)) {
            try {
                if ($mode === 'compact') {
                    \Native\Desktop\Facades\Window::resize(1080, 720, $id);
                } elseif ($mode === 'fullscreen') {
                    \Native\Desktop\Facades\Window::maximize($id);
                }
            } catch (\Throwable $e) {}
        }
        return response()->json(['success' => true, 'mode' => $mode]);
    });

    Route::get('/window/status', function (\Illuminate\Http\Request $request) {
        $id = $request->query('id', 'main');
        if (class_exists(\Native\Desktop\Client\Client::class)) {
            try {
                $client = app(\Native\Desktop\Client\Client::class);
                $res = $client->get('window/status', ['id' => $id]);
                if ($res->successful()) {
                    return response()->json($res->json());
                }
            } catch (\Throwable $e) {}
        }
        return response()->json(['isMaximized' => false]);
    });
});
