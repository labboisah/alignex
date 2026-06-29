<?php

use App\Http\Controllers\Api\CandidateExamController;
use App\Http\Controllers\ProfessionalExamController;
use App\Http\Controllers\ResultController;
use Illuminate\Support\Facades\Route;

Route::prefix('candidate')->group(function (): void {
    Route::post('/login', [CandidateExamController::class, 'login']);
    Route::get('/exam', [CandidateExamController::class, 'exam']);
    Route::post('/answer', [CandidateExamController::class, 'answer']);
    Route::post('/submit', [CandidateExamController::class, 'submit']);
    Route::post('/auto-submit', [CandidateExamController::class, 'autoSubmit']);
    Route::post('/event', [CandidateExamController::class, 'event']);
    Route::post('/result', [ResultController::class, 'candidateResult']);
});

Route::post('/results/verify', [ResultController::class, 'verify']);
Route::post('/certificates/verify', [ProfessionalExamController::class, 'verify']);
