<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrganisationProfileController;
use App\Http\Controllers\Api\ProgrammeEntryController;
use App\Http\Controllers\Api\ProgrammeActivityController;
use App\Http\Controllers\Api\ProgrammeGeographyController;
use App\Http\Controllers\Api\EntryKeywordController;
use App\Http\Controllers\Api\GovernmentAgreementController;
use App\Http\Controllers\Api\Admin\OrganisationController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MapEntryController;
use App\Http\Controllers\Api\AdviserMapOverlapController;
use App\Http\Controllers\Api\TaxonomyController;
use App\Http\Controllers\Api\AdviserSubmissionController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PolicyDocumentController as ApiPolicyDocumentController;
use App\Http\Controllers\Api\RefdataController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PasswordResetController;

Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/provinces', [LocationController::class, 'index']);
    Route::get('/provinces/{province}/districts', [LocationController::class, 'districts']);
    Route::get('/districts/{district}/communes', [LocationController::class, 'communes']);
    Route::get('/communes/{commune}/villages', [LocationController::class, 'villages']);

    Route::get('/refdata/education-levels', [RefdataController::class, 'educationLevels']);
    Route::get('/refdata/budget-bands', [RefdataController::class, 'budgetBands']);
    Route::get('/refdata/counterpart-agencies', [RefdataController::class, 'counterpartAgencies']);

    Route::get('/user', fn(Request $request) => $request->user());
    Route::get('/session', [AuthController::class, 'session']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/programme-entries', [ProgrammeEntryController::class, 'getAll']);
    Route::post('/programme-entries', [ProgrammeEntryController::class, 'store']);
    Route::put('/programme-entries/{programmeEntry}', [ProgrammeEntryController::class, 'update']);
    Route::get('/programme-entries/draft', [ProgrammeEntryController::class, 'draft']);
    Route::get('/programme-entries/submitted', [ProgrammeEntryController::class, 'submitted']);
    Route::get('/programme-entries/{programmeEntry}', [ProgrammeEntryController::class, 'show']);
    Route::get('/organisations/{organisation}/programme-entries', [ProgrammeEntryController::class, 'index']);

    Route::get('/organisations/me', [OrganisationProfileController::class, 'show']);
    Route::patch('/organisations/me', [OrganisationProfileController::class, 'update']);

    Route::patch('/programme-entries/{programmeEntry}/verify', [ProgrammeEntryController::class, 'verify'])
        ->middleware('role:nep_admin');

    Route::middleware('role:nep_admin,member_org')->group(function () {
        Route::post('/programme-entries/{programmeEntry}/activities', [ProgrammeActivityController::class, 'store']);
        Route::put('/programme-entries/{programmeEntry}/keywords', [EntryKeywordController::class, 'store']);
        Route::put('/programme-entries/{programmeEntry}/geography', [ProgrammeGeographyController::class, 'store']);
        Route::put('/programme-entries/{programmeEntry}/government-agreements', [GovernmentAgreementController::class, 'store']);
    });

    Route::middleware('role:nep_admin,nep_coordinator,member_org')->group(function () {
        Route::get('/programme-entries/{programmeEntry}/geography', [ProgrammeGeographyController::class, 'index']);
        Route::get('/taxonomy/categories', [TaxonomyController::class, 'listCategories']);
        Route::get('/policy-documents', [ApiPolicyDocumentController::class, 'index']);
        Route::get('/policy-documents/{policyDocument}', [ApiPolicyDocumentController::class, 'show']);
    });

    Route::middleware('role:nep_admin,nep_coordinator')->group(function () {
        Route::post('/policy-documents', [ApiPolicyDocumentController::class, 'store']);
        Route::patch('/policy-documents/{policyDocument}', [ApiPolicyDocumentController::class, 'update']);
        Route::delete('/policy-documents/{policyDocument}', [ApiPolicyDocumentController::class, 'destroy']);

        Route::get('/provinces/counts', [LocationController::class, 'provinceProgrammeCounts']);
        Route::get('/taxonomy/categories/counts', [TaxonomyController::class, 'categoryProgrammeCounts']);
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('/dashboard/recent-activity', [DashboardController::class, 'recentActivity']);
        Route::get('/adviser/submissions', [AdviserSubmissionController::class, 'index']);

        Route::get('/map/entries', [MapEntryController::class, 'index']);
        Route::get('/map/entries/export', [MapEntryController::class, 'export']);
        Route::get('/map/entries/export/pdf', [MapEntryController::class, 'exportPdf']);
        Route::get('/map/entries/geojson', [MapEntryController::class, 'geojson']);
        Route::post('/adviser/submissions', [AdviserSubmissionController::class, 'store'])
            ->middleware('throttle:10,1'); // Rate limit: 10 requests per minute

        Route::get('/adviser/submissions/{advisoryNote}', [AdviserSubmissionController::class, 'show']);
        Route::patch('/adviser/submissions/{advisoryNote}', [AdviserSubmissionController::class, 'update']);
<<<<<<< HEAD
        Route::patch('/adviser/submissions/{advisoryNote}/deliver', [AdviserSubmissionController::class, 'markDelivered']);

=======
        Route::patch('/adviser/submissions/{advisoryNote}/deliver', [AdviserSubmissionController::class, 'markDelivered'])
            ->middleware('throttle:10,1');
>>>>>>> fcbf1472680fb8d03c3d4e34503ead872f7c469c
        Route::post('/adviser/submissions/{id}/generate-advisory-note', [AdviserSubmissionController::class, 'generateAdvisoryNote'])
            ->middleware('throttle:5,1');
        Route::post('/adviser/map/overlap-query', [AdviserMapOverlapController::class, 'match']);
    });

    Route::middleware('role:nep_admin')->prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])->name('index');
        Route::post('/', [UserManagementController::class, 'store'])->name('store');
        Route::post('/invite', [UserManagementController::class, 'invite'])->name('invite');
        Route::patch('/{user}', [UserManagementController::class, 'update'])->name('update');
        Route::post('/{user}/deactivate', [UserManagementController::class, 'deactivate'])->name('deactivate');
        Route::post('/{user}/reactivate', [UserManagementController::class, 'reactivate'])->name('reactivate');
        Route::post('/{user}/reset-credentials', [UserManagementController::class, 'resetCredentials'])->name('reset-credentials');
    });

    Route::middleware('role:nep_admin')->prefix('admin/organisations')->name('admin.organisations.')->group(function () {
        Route::get('/', [OrganisationController::class, 'index'])->name('index');
        Route::post('/', [OrganisationController::class, 'store'])->name('store');
        Route::post('/{organisation}/logo', [OrganisationController::class, 'uploadLogo'])->name('logo');
        Route::get('/{organisation}', [OrganisationController::class, 'show'])->name('show');
        Route::put('/{organisation}', [OrganisationController::class, 'update'])->name('update');
        Route::patch('/{organisation}/deactivate', [OrganisationController::class, 'deactivate'])->name('deactivate');
        Route::patch('/{organisation}/activate', [OrganisationController::class, 'activate'])->name('activate');
    });

    Route::middleware('role:nep_admin')->prefix('taxonomy')->group(function () {
        // Categories
        Route::post('/categories', [TaxonomyController::class, 'createCategory']);
        Route::put('/categories/{category}', [TaxonomyController::class, 'renameCategory']);
        Route::patch('/categories/{category}/deprecate', [TaxonomyController::class, 'deprecateCategory']);

        Route::post('/subcategories', [TaxonomyController::class, 'createSubcategory']);
        Route::put('/subcategories/{subcategory}', [TaxonomyController::class, 'renameSubcategory']);
        Route::patch('/subcategories/{subcategory}/deprecate', [TaxonomyController::class, 'deprecateSubcategory']);

        Route::post('/items', [TaxonomyController::class, 'createItem']);
        Route::put('/items/{item}', [TaxonomyController::class, 'renameItem']);
        Route::patch('/items/{item}/deprecate', [TaxonomyController::class, 'deprecateItem']);

        // Other Entries Review
        Route::get('/other-entries', [TaxonomyController::class, 'listOtherEntries']);
    });
});