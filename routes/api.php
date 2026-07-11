<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProgrammeEntryController;
use App\Http\Controllers\Api\ProgrammeActivityController;
use App\Http\Controllers\Api\ProgrammeGeographyController;
use App\Http\Controllers\Api\GovernmentAgreementController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
    Route::get('/session', [AuthController::class, 'session']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/programme-entries', [ProgrammeEntryController::class, 'store']);
    Route::put('/programme-entries/{programmeEntry}', [ProgrammeEntryController::class, 'update']);
    Route::get('/programme-entries/{programmeEntry}', [ProgrammeEntryController::class, 'show']);

    Route::get('/organisations/{organisation}/programme-entries', [ProgrammeEntryController::class, 'index']);

    Route::post('/programme-entries/{programmeEntry}/activities', [ProgrammeActivityController::class, 'store']);

    Route::get('/programme-entries/{programmeEntry}/geography', [ProgrammeGeographyController::class, 'index']);
    Route::put('/programme-entries/{programmeEntry}/geography', [ProgrammeGeographyController::class, 'store']);

    Route::put('/programme-entries/{programmeEntry}/government-agreements', [GovernmentAgreementController::class, 'store']);
});