<?php

use App\Http\Controllers\GenerationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// API Endpoints untuk Desktop Frontend DEVArchitect
Route::prefix('api')->group(function () {
    // 1. Modul Proyek
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects/browse', [ProjectController::class, 'browse']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);

    // 2. Modul AI Generator & Dry-Run
    Route::post('/generations/generate', [GenerationController::class, 'generate']);
    Route::get('/generations/{id}', [GenerationController::class, 'show']);
    Route::put('/generations/{id}', [GenerationController::class, 'update']);
    Route::post('/generations/{id}/inject', [GenerationController::class, 'inject']);

    // 3. Modul Pengaturan
    Route::get('/settings', [SettingController::class, 'index']);
    Route::post('/settings', [SettingController::class, 'update']);
});
