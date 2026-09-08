<?php

use App\Http\Controllers\Api\AdaptiveCandidateController;
use App\Http\Controllers\Api\AdaptiveOfflinePilotController;
use App\Http\Controllers\Api\CandidateExamController;
use App\Http\Controllers\Api\OfflineExamPackageController;
use App\Http\Controllers\Api\OfflineServerActivationController;
use App\Http\Controllers\Api\OfflineUpdateController;
use App\Http\Controllers\ProfessionalExamController;
use App\Http\Controllers\ResultController;
use Illuminate\Support\Facades\Route;

Route::prefix('candidate')->group(function (): void {
    Route::post('/login', [CandidateExamController::class, 'login']);
    Route::post('/start', [CandidateExamController::class, 'start']);
    Route::get('/exam', [CandidateExamController::class, 'exam']);
    Route::post('/next-level', [AdaptiveCandidateController::class, 'nextLevel'])->middleware('throttle:30,1');
    Route::post('/answer', [CandidateExamController::class, 'answer']);
    Route::post('/submit', [CandidateExamController::class, 'submit']);
    Route::post('/auto-submit', [CandidateExamController::class, 'autoSubmit']);
    Route::post('/event', [CandidateExamController::class, 'event']);
    Route::post('/result', [ResultController::class, 'candidateResult']);
});

Route::post('/results/verify', [ResultController::class, 'verify']);
Route::post('/certificates/verify', [ProfessionalExamController::class, 'verify']);
Route::post('/offline/activate', [OfflineServerActivationController::class, 'store']);
Route::get('/offline/exam-packages/{examCode}', [OfflineExamPackageController::class, 'show']);
Route::get('/offline/updates', [OfflineUpdateController::class, 'index']);
Route::get('/offline/updates/{artifact}/download', [OfflineUpdateController::class, 'download']);

Route::get('/offline/adaptive/packages/{package}', [AdaptiveOfflinePilotController::class, 'package'])->middleware('throttle:10,1');
Route::post('/offline/adaptive/leases/{lease}/sync', [AdaptiveOfflinePilotController::class, 'sync'])->middleware('throttle:10,1');
