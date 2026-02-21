<?php

use App\Http\Controllers\Company\CompanyUserController;
use App\Http\Controllers\Company\DashboardController;
use App\Http\Controllers\Import\ImportController;
use App\Http\Controllers\Platform\PlatformAdminController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Shipment\ShipmentController;
use App\Http\Controllers\Sms\SmsCampaignController;
use App\Http\Controllers\Telemarketing\TelemarketingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('login');
});

// ── Authenticated Routes ──
Route::middleware(['auth', 'verified'])->group(function () {

    // Profile (Breeze default)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ── Company-Scoped Routes ──
    Route::middleware([\App\Http\Middleware\EnsureCompanyScope::class])->group(function () {

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Import
        Route::middleware('can:import.upload')->group(function () {
            Route::get('/import/create', [ImportController::class, 'create'])->name('import.create');
            Route::post('/import', [ImportController::class, 'store'])->name('import.store');
        });
        Route::middleware('can:import.view')->group(function () {
            Route::get('/import', [ImportController::class, 'index'])->name('import.index');
            Route::get('/import/{importJob}', [ImportController::class, 'show'])->name('import.show');
        });

        // Shipments
        Route::middleware('can:shipments.view')->group(function () {
            Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index');
            Route::get('/shipments/{shipment}', [ShipmentController::class, 'show'])->name('shipments.show');
        });
        Route::middleware('can:shipments.assign')->group(function () {
            Route::post('/shipments/bulk-assign', [ShipmentController::class, 'bulkAssign'])->name('shipments.bulk-assign');
            Route::post('/shipments/bulk-unassign', [ShipmentController::class, 'bulkUnassign'])->name('shipments.bulk-unassign');
        });
        Route::middleware('can:shipments.auto-assign')->group(function () {
            Route::post('/shipments/auto-assign', [ShipmentController::class, 'autoAssign'])->name('shipments.auto-assign');
        });

        // Telemarketing
        Route::middleware('can:telemarketing.view-queue')->group(function () {
            Route::get('/telemarketing/queue', [TelemarketingController::class, 'queue'])->name('telemarketing.queue');
            Route::get('/telemarketing/call/{shipment}', [TelemarketingController::class, 'callForm'])->name('telemarketing.call');
        });
        Route::middleware('can:telemarketing.log-call')->group(function () {
            Route::post('/telemarketing/call/{shipment}', [TelemarketingController::class, 'logCall'])->name('telemarketing.log-call');
        });

        // SMS Campaigns
        Route::middleware('can:sms.campaigns.view')->group(function () {
            Route::get('/sms/campaigns', [SmsCampaignController::class, 'index'])->name('sms.campaigns.index');
            Route::get('/sms/campaigns/{campaign}/logs', [SmsCampaignController::class, 'logs'])->name('sms.campaigns.logs');
        });
        Route::middleware('can:sms.campaigns.create')->group(function () {
            Route::get('/sms/campaigns/create', [SmsCampaignController::class, 'create'])->name('sms.campaigns.create');
            Route::post('/sms/campaigns', [SmsCampaignController::class, 'store'])->name('sms.campaigns.store');
        });
        Route::middleware('can:sms.campaigns.edit')->group(function () {
            Route::get('/sms/campaigns/{campaign}/edit', [SmsCampaignController::class, 'edit'])->name('sms.campaigns.edit');
            Route::put('/sms/campaigns/{campaign}', [SmsCampaignController::class, 'update'])->name('sms.campaigns.update');
        });
        Route::middleware('can:sms.campaigns.toggle')->group(function () {
            Route::post('/sms/campaigns/{campaign}/toggle', [SmsCampaignController::class, 'toggle'])->name('sms.campaigns.toggle');
        });

        // Company User Management
        Route::middleware('can:users.view')->group(function () {
            Route::get('/company/users', [CompanyUserController::class, 'index'])->name('company.users.index');
        });
        Route::middleware('can:users.create')->group(function () {
            Route::get('/company/users/create', [CompanyUserController::class, 'create'])->name('company.users.create');
            Route::post('/company/users', [CompanyUserController::class, 'store'])->name('company.users.store');
        });
        Route::middleware('can:users.edit')->group(function () {
            Route::get('/company/users/{user}/edit', [CompanyUserController::class, 'edit'])->name('company.users.edit');
            Route::put('/company/users/{user}', [CompanyUserController::class, 'update'])->name('company.users.update');
        });
        Route::middleware('can:users.toggle')->group(function () {
            Route::post('/company/users/{user}/toggle', [CompanyUserController::class, 'toggle'])->name('company.users.toggle');
        });
    });

    // ── Platform Admin Routes ──
    Route::middleware([\App\Http\Middleware\EnsurePlatformAdmin::class])->prefix('platform')->name('platform.')->group(function () {
        Route::get('/dashboard', [PlatformAdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/companies', [PlatformAdminController::class, 'companies'])->name('companies.index');
        Route::get('/companies/{company}', [PlatformAdminController::class, 'showCompany'])->name('companies.show');
        Route::post('/companies/{company}/toggle', [PlatformAdminController::class, 'toggleCompany'])->name('companies.toggle');
    });
});

require __DIR__.'/auth.php';
