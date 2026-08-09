<?php

use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrganisationProfileController;
use App\Http\Controllers\Api\ProgrammeEntryController;
use App\Http\Controllers\Api\ProgrammeActivityController;
use App\Http\Controllers\Api\ProgrammeGeographyController;
use App\Http\Controllers\Api\EntryKeywordController;
use App\Http\Controllers\Api\GovernmentAgreementController;
use App\Http\Controllers\Api\Admin\OrganisationController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Admin\RoleManagementController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MapEntryController;
use App\Http\Controllers\Api\MapExportController;
use App\Http\Controllers\Api\MapGeoJsonController;
use App\Http\Controllers\Api\AdviserMapOverlapController;
use App\Http\Controllers\Api\TaxonomyController;
use App\Http\Controllers\Api\AdviserSubmissionController;
use App\Http\Controllers\Api\ProgrammeActivityAiController;
use App\Http\Controllers\Api\AdviserAnalysisController;
use App\Http\Controllers\Api\AdviserProgrammeEntryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PolicyDocumentController as ApiPolicyDocumentController;
use App\Http\Controllers\Api\RefdataController;
use App\Http\Controllers\Api\PasswordResetController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
Route::get('/adviser/submissions/{advisoryNote}/file', [AdviserSubmissionController::class, 'downloadFile']);

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

    Route::get('/user', fn(Request $request) => $request->user()->only(
        'id', 'name', 'email', 'role', 'status', 'organisation_id'
    ));
    Route::get('/session', [AuthController::class, 'session']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::patch('/change-password', [AuthController::class, 'changePassword']);

    // Session/device management — view and revoke individual logged-in devices
    Route::get('/sessions', function (Request $request) {
        return $request->user()->tokens()
            ->select('id', 'name', 'last_used_at', 'created_at')
            ->get();
    });

    Route::delete('/sessions/{tokenId}', function (Request $request, $tokenId) {
        $deleted = $request->user()->tokens()->where('id', $tokenId)->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Session not found.'], 404);
        }

        return response()->json(['message' => 'Session revoked.']);
    });

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::get('/programme-entries', [ProgrammeEntryController::class, 'getAll']);
    Route::post('/programme-entries', [ProgrammeEntryController::class, 'store']);
    Route::put('/programme-entries/{programmeEntry}', [ProgrammeEntryController::class, 'update']);
    Route::get('/programme-entries/draft', [ProgrammeEntryController::class, 'draft']);
    Route::get('/programme-entries/my-drafts', [ProgrammeEntryController::class, 'myDrafts']);
    Route::get('/programme-entries/submitted', [ProgrammeEntryController::class, 'submitted']);
    Route::get('/programme-entries/{programmeEntry}', [ProgrammeEntryController::class, 'show']);
    Route::get('/programme-entries/{programmeEntry}/pdf', [ProgrammeEntryController::class, 'exportPdf']);
    Route::get('/organisations/{organisation}/programme-entries', [ProgrammeEntryController::class, 'index']);
    Route::get('/organisations/{organisation}/programme-entries/pdf', [ProgrammeEntryController::class, 'exportOrganisationProgrammesPdf']);

    Route::get('/organisations/me', [OrganisationProfileController::class, 'show']);
    Route::patch('/organisations/me', [OrganisationProfileController::class, 'update']);

    Route::patch('/programme-entries/{programmeEntry}/verify', [ProgrammeEntryController::class, 'verify'])
        ->middleware('role:nep_admin');

    Route::middleware('role:nep_admin,nep_coordinator,member_org')->group(function () {
        Route::get('/programme-entries/{programmeEntry}/activities', [ProgrammeActivityController::class, 'index']);
        Route::post('/programme-entries/{programmeEntry}/activities', [ProgrammeActivityController::class, 'store']);
        Route::put('/programme-entries/{programmeEntry}/keywords', [EntryKeywordController::class, 'store']);
        Route::put('/programme-entries/{programmeEntry}/geography', [ProgrammeGeographyController::class, 'store']);
        Route::put('/programme-entries/{programmeEntry}/government-agreements', [GovernmentAgreementController::class, 'store']);

        Route::post('/programme-entries/suggest-activities', [ProgrammeActivityAiController::class, 'suggestActivities'])->middleware('throttle:10,1');
        Route::post('/programme-entries/fetch-url', [ProgrammeActivityAiController::class, 'fetchUrl'])->middleware('throttle:20,1');
        Route::post('/programme-entries/ai-autofill', [ProgrammeActivityAiController::class, 'aiAutofill'])->middleware('throttle:10,1');
    });

    Route::middleware('role:nep_admin,nep_coordinator,member_org')->group(function () {
        Route::get('/programme-entries/{programmeEntry}/geography', [ProgrammeGeographyController::class, 'index']);
        Route::get('/programme-entries/{programmeEntry}/government-agreements', [GovernmentAgreementController::class, 'index']);
        Route::get('/taxonomy/categories', [TaxonomyController::class, 'listCategories']);
    });

    // Member orgs can view the delivered advisory note for their own programme entries
    Route::get('/adviser/programme-entries/{programmeEntry}/advisory-note', [AdviserSubmissionController::class, 'showByProgrammeEntry'])
        ->middleware('role:nep_admin,nep_coordinator,member_org');

    Route::middleware('role:nep_admin,nep_coordinator,member_org')->group(function () {
        Route::get('/policy-documents', [ApiPolicyDocumentController::class, 'index']);
        Route::get('/policy-documents/{policyDocument}', [ApiPolicyDocumentController::class, 'show']);
        Route::get('/policy-documents/{policyDocument}/file', [ApiPolicyDocumentController::class, 'getFile']);
    });

    Route::middleware('role:nep_admin,nep_coordinator')->group(function () {
        Route::post('/policy-documents', [ApiPolicyDocumentController::class, 'store']);
        Route::patch('/policy-documents/{policyDocument}', [ApiPolicyDocumentController::class, 'update']);
    });

    Route::middleware('role:nep_admin')->group(function () {
        Route::delete('/policy-documents/{policyDocument}', [ApiPolicyDocumentController::class, 'destroy']);
    });

    Route::middleware('role:nep_admin,nep_coordinator')->group(function () {
        Route::get('/provinces/counts', [LocationController::class, 'provinceProgrammeCounts']);
        Route::get('/taxonomy/categories/counts', [TaxonomyController::class, 'categoryProgrammeCounts']);
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('/dashboard/recent-activity', [DashboardController::class, 'recentActivity']);
        Route::get('/adviser/submissions', [AdviserSubmissionController::class, 'index']);
        Route::get('/adviser/coordinators', [AdviserSubmissionController::class, 'coordinators']);

        Route::get('/map/entries', [MapEntryController::class, 'index']);
        Route::get('/map/entries/export', [MapExportController::class, 'export']);
        Route::get('/map/entries/export/pdf', [MapExportController::class, 'exportPdf']);
        Route::get('/map/entries/geojson', [MapGeoJsonController::class, 'geojson']);

        Route::post('/adviser/submissions', [AdviserSubmissionController::class, 'store'])
            ->middleware('throttle:10,1');

        Route::get('/adviser/submissions/{advisoryNote}', [AdviserSubmissionController::class, 'show']);
        Route::get('/adviser/submissions/{advisoryNote}/export-pdf', [AdviserSubmissionController::class, 'exportPdf']);
        Route::patch('/adviser/submissions/{advisoryNote}', [AdviserSubmissionController::class, 'update']);
        Route::post('/adviser/submissions/{advisoryNote}/file-token', [AdviserSubmissionController::class, 'fileToken']);
        Route::patch('/adviser/submissions/{advisoryNote}/deliver', [AdviserSubmissionController::class, 'markDelivered'])
            ->middleware('throttle:10,1');

        Route::post('/adviser/map/overlap-query', [AdviserMapOverlapController::class, 'match']);

        Route::post('/adviser/submissions/{id}/parse-pdf', [AdviserAnalysisController::class, 'parsePdf']);
        Route::get('/adviser/submissions/{id}/parse-document', [AdviserAnalysisController::class, 'parseDocument']);
        Route::post('/adviser/submissions/{id}/extract-profile', [AdviserAnalysisController::class, 'extractProfile'])->middleware('throttle:10,1');
        Route::post('/adviser/submissions/{id}/generate-advisory-note', [AdviserAnalysisController::class, 'generateAdvisoryNote']);
        Route::post('/adviser/submissions/{id}/create-programme-entry', [AdviserProgrammeEntryController::class, 'createProgrammeEntry']);
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

    Route::middleware('role:nep_admin')->prefix('admin/roles')->name('admin.roles.')->group(function () {
        Route::get('/', [RoleManagementController::class, 'index'])->name('index');
        Route::get('/{role}', [RoleManagementController::class, 'show'])->name('show');
        Route::post('/', [RoleManagementController::class, 'store'])->name('store');
        Route::patch('/{role}', [RoleManagementController::class, 'update'])->name('update');
        Route::delete('/{role}', [RoleManagementController::class, 'destroy'])->name('destroy');
        
        Route::post('/{role}/users/{user}', [RoleManagementController::class, 'assignToUser'])->name('assign-to-user');
        Route::delete('/{role}/users/{user}', [RoleManagementController::class, 'removeFromUser'])->name('remove-from-user');
    });

    Route::middleware('role:nep_admin')->prefix('admin/permissions')->name('admin.permissions.')->group(function () {
        Route::get('/', [RoleManagementController::class, 'permissions'])->name('index');
        Route::post('/', [RoleManagementController::class, 'storePermission'])->name('store');
        Route::patch('/{permission}', [RoleManagementController::class, 'updatePermission'])->name('update');
        Route::delete('/{permission}', [RoleManagementController::class, 'destroyPermission'])->name('destroy');
    });

    Route::middleware('role:nep_admin,nep_coordinator')->prefix('admin/organisations')->name('admin.organisations.')->group(function () {
        Route::get('/', [OrganisationController::class, 'index'])->name('index');
        Route::get('/{organisation}', [OrganisationController::class, 'show'])->name('show');
    });

    Route::middleware('role:nep_admin')->prefix('admin/organisations')->name('admin.organisations.write.')->group(function () {
        Route::post('/', [OrganisationController::class, 'store'])->name('store');
        Route::post('/{organisation}/logo', [OrganisationController::class, 'uploadLogo'])->name('logo');
        Route::put('/{organisation}', [OrganisationController::class, 'update'])->name('update');
        Route::patch('/{organisation}/deactivate', [OrganisationController::class, 'deactivate'])->name('deactivate');
        Route::patch('/{organisation}/activate', [OrganisationController::class, 'activate'])->name('activate');
    });

    Route::middleware('role:nep_admin')->prefix('taxonomy')->group(function () {
        Route::post('/categories', [TaxonomyController::class, 'createCategory']);
        Route::put('/categories/{category}', [TaxonomyController::class, 'renameCategory']);
        Route::patch('/categories/{category}/deprecate', [TaxonomyController::class, 'deprecateCategory']);

        Route::post('/subcategories', [TaxonomyController::class, 'createSubcategory']);
        Route::put('/subcategories/{subcategory}', [TaxonomyController::class, 'renameSubcategory']);
        Route::patch('/subcategories/{subcategory}/deprecate', [TaxonomyController::class, 'deprecateSubcategory']);

        Route::post('/items', [TaxonomyController::class, 'createItem']);
        Route::put('/items/{item}', [TaxonomyController::class, 'renameItem']);
        Route::patch('/items/{item}/deprecate', [TaxonomyController::class, 'deprecateItem']);

        Route::get('/other-entries', [TaxonomyController::class, 'listOtherEntries']);
    });
});
