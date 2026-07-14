<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProgrammeEntryController;
use App\Http\Controllers\Api\ProgrammeActivityController;
use App\Http\Controllers\Api\ProgrammeGeographyController;
use App\Http\Controllers\Api\GovernmentAgreementController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MapEntryController;
use App\Http\Controllers\Api\TaxonomyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/provinces', [LocationController::class, 'index']);
    Route::get('/provinces/{province}/districts', [LocationController::class, 'districts']);

    Route::get('/user', fn (Request $request) => $request->user());
    Route::get('/session', [AuthController::class, 'session']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/programme-entries', [ProgrammeEntryController::class, 'store']);
    Route::put('/programme-entries/{programmeEntry}', [ProgrammeEntryController::class, 'update']);
    Route::get('/programme-entries/{programmeEntry}', [ProgrammeEntryController::class, 'show']);
    Route::get('/organisations/{organisation}/programme-entries', [ProgrammeEntryController::class, 'index']);

    Route::patch('/programme-entries/{programmeEntry}/verify', [ProgrammeEntryController::class, 'verify'])
        ->middleware('role:nep_admin');

    Route::middleware('role:nep_admin,member_org')->group(function () {
        Route::post('/programme-entries/{programmeEntry}/activities', [ProgrammeActivityController::class, 'store']);
        Route::put('/programme-entries/{programmeEntry}/geography', [ProgrammeGeographyController::class, 'store']);
        Route::put('/programme-entries/{programmeEntry}/government-agreements', [GovernmentAgreementController::class, 'store']);
    });

    Route::middleware('role:nep_admin,nep_coordinator,member_org')->group(function () {
        Route::get('/programme-entries/{programmeEntry}/geography', [ProgrammeGeographyController::class, 'index']);
        Route::get('/map/entries', [MapEntryController::class, 'index']);
        Route::get('/map/entries/export', [MapEntryController::class, 'export']);
        Route::get('/map/entries/export/pdf', [MapEntryController::class, 'exportPdf']);
    });

    // Taxonomy Management - NEP Admin only
    Route::middleware('role:nep_admin')->prefix('taxonomy')->group(function () {
        // Categories
        Route::get('/categories', [TaxonomyController::class, 'listCategories']);
        Route::post('/categories', [TaxonomyController::class, 'createCategory']);
        Route::put('/categories/{category}', [TaxonomyController::class, 'renameCategory']);
        Route::patch('/categories/{category}/deprecate', [TaxonomyController::class, 'deprecateCategory']);

        // Subcategories
        Route::post('/subcategories', [TaxonomyController::class, 'createSubcategory']);
        Route::put('/subcategories/{subcategory}', [TaxonomyController::class, 'renameSubcategory']);
        Route::patch('/subcategories/{subcategory}/deprecate', [TaxonomyController::class, 'deprecateSubcategory']);

        // Items
        Route::post('/items', [TaxonomyController::class, 'createItem']);
        Route::put('/items/{item}', [TaxonomyController::class, 'renameItem']);
        Route::patch('/items/{item}/deprecate', [TaxonomyController::class, 'deprecateItem']);
    });
});
