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
use App\Http\Controllers\Api\RefdataController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

    Route::get('/notifications', fn(Request $request) => response()->json([
        'data' => $request->user()->notifications()->latest()->take(20)->get()->map(fn($n) => [
            'id'                 => $n->id,
            'type'               => 'programme_sent',
            'title'              => 'New programme: ' . ($n->data['programme_name'] ?? ''),
            'message'            => $n->data['message'] ?? '',
            'programme_entry_id' => $n->data['programme_entry_id'] ?? null,
            'read_at'            => $n->read_at,
            'created_at'         => $n->created_at,
        ]),
    ]));
    Route::patch('/notifications/read-all', function (Request $request) {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['message' => 'All notifications marked as read.']);
    });
    Route::patch('/notifications/{id}/read', function (Request $request, string $id) {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        return response()->json(['message' => 'Notification marked as read.']);
    });

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

    Route::post('/programme-entries/{programmeEntry}/send-to-member', [ProgrammeEntryController::class, 'sendToMember'])
        ->middleware('role:nep_admin,nep_coordinator');

    Route::middleware('role:nep_admin,nep_coordinator,member_org')->group(function () {
        Route::post('/programme-entries/{programmeEntry}/activities', [ProgrammeActivityController::class, 'store']);
        Route::put('/programme-entries/{programmeEntry}/keywords', [EntryKeywordController::class, 'store']);
        Route::put('/programme-entries/{programmeEntry}/geography', [ProgrammeGeographyController::class, 'store']);
        Route::put('/programme-entries/{programmeEntry}/government-agreements', [GovernmentAgreementController::class, 'store']);
    });

    Route::middleware('role:nep_admin,nep_coordinator,member_org')->group(function () {
        Route::get('/programme-entries/{programmeEntry}/geography', [ProgrammeGeographyController::class, 'index']);
        Route::get('/map/entries', [MapEntryController::class, 'index']);
        Route::get('/map/entries/export', [MapEntryController::class, 'export']);
        Route::get('/map/entries/export/pdf', [MapEntryController::class, 'exportPdf']);
        Route::get('/taxonomy/categories', [TaxonomyController::class, 'listCategories']);
    });

    Route::middleware('role:nep_admin,nep_coordinator')->group(function () {
        Route::post('/adviser/submissions', [AdviserSubmissionController::class, 'store'])
            ->middleware('throttle:10,1');
        Route::post('/adviser/map/overlap-query', [AdviserMapOverlapController::class, 'match']);
    });

    Route::middleware('role:nep_admin')->prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])->name('index');
        Route::post('/', [UserManagementController::class, 'store'])->name('store');
        Route::patch('/{user}', [UserManagementController::class, 'update'])->name('update');
        Route::post('/{user}/deactivate', [UserManagementController::class, 'deactivate'])->name('deactivate');
        Route::post('/{user}/reactivate', [UserManagementController::class, 'reactivate'])->name('reactivate');
        Route::post('/{user}/reset-credentials', [UserManagementController::class, 'resetCredentials'])->name('reset-credentials');
    });

    Route::middleware('role:nep_admin,nep_coordinator')->prefix('admin/organisations')->name('admin.organisations.')->group(function () {
        Route::get('/', [OrganisationController::class, 'index'])->name('index');
    });

    Route::middleware('role:nep_admin')->prefix('admin/organisations')->name('admin.organisations.')->group(function () {
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
