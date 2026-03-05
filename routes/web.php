<?php

use App\Http\Controllers\Company\CompanyUserController;
use App\Http\Controllers\Company\DashboardController;
use App\Http\Controllers\Company\RemittanceController;
use App\Http\Controllers\Company\SettingsController;
use App\Http\Controllers\Import\ImportController;
use App\Http\Controllers\Platform\PlatformAdminController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Shipment\ShipmentController;
use App\Http\Controllers\Sms\SmsCampaignController;
use App\Http\Controllers\Sms\SmsDeviceController;
use App\Http\Controllers\Telemarketing\TelemarketingController;
use App\Http\Controllers\Api\RecordingController;
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
        Route::middleware('company.can:import.upload')->group(function () {
            Route::get('/import/create', [ImportController::class, 'create'])->name('import.create');
            Route::post('/import', [ImportController::class, 'store'])->name('import.store');
        });
        Route::middleware('company.can:import.view')->group(function () {
            Route::get('/import', [ImportController::class, 'index'])->name('import.index');
            Route::get('/import/{importJob}', [ImportController::class, 'show'])->name('import.show');
            Route::get('/import/{importJob}/status', [ImportController::class, 'status'])->name('import.status');
            Route::post('/import/status-batch', [ImportController::class, 'statusBatch'])->name('import.status-batch');
        });

        // Shipments
        Route::middleware('company.can:shipments.view')->group(function () {
            Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index');
            Route::get('/shipments/{shipment}', [ShipmentController::class, 'show'])->name('shipments.show');
        });
        Route::middleware('company.can:shipments.assign')->group(function () {
            Route::post('/shipments/bulk-assign', [ShipmentController::class, 'bulkAssign'])->name('shipments.bulk-assign');
            Route::post('/shipments/bulk-unassign', [ShipmentController::class, 'bulkUnassign'])->name('shipments.bulk-unassign');
        });
        Route::middleware('company.can:shipments.auto-assign')->group(function () {
            Route::post('/shipments/auto-assign', [ShipmentController::class, 'autoAssign'])->name('shipments.auto-assign');
        });

        // ── Telemarketing Module ──
        // Dashboard (all roles with telemarketing access)
        Route::middleware('company.can:telemarketing.view-queue')->group(function () {
            Route::get('/telemarketing', [TelemarketingController::class, 'dashboard'])->name('telemarketing.dashboard');
            Route::get('/telemarketing/queue', [TelemarketingController::class, 'queue'])->name('telemarketing.queue');
            Route::get('/telemarketing/next-call', [TelemarketingController::class, 'nextCall'])->name('telemarketing.next-call');
            Route::get('/telemarketing/call/{shipment}', [TelemarketingController::class, 'callForm'])->name('telemarketing.call');
        });
        Route::middleware('company.can:telemarketing.log-call')->group(function () {
            Route::post('/telemarketing/call/{shipment}', [TelemarketingController::class, 'logCall'])->name('telemarketing.log-call');
        });
        // Manager-only telemarketing routes
        Route::middleware('company.can:telemarketing.view-all-logs')->group(function () {
            Route::get('/telemarketing/assignments', [TelemarketingController::class, 'assignments'])->name('telemarketing.assignments');
            Route::post('/telemarketing/manual-assign', [TelemarketingController::class, 'manualAssign'])->name('telemarketing.manual-assign');
            Route::post('/telemarketing/unassign-all', [TelemarketingController::class, 'unassignAll'])->name('telemarketing.unassign-all');
            Route::post('/telemarketing/run-auto-assign', [TelemarketingController::class, 'runAutoAssign'])->name('telemarketing.run-auto-assign');
            Route::post('/telemarketing/sync-agent-statuses', [TelemarketingController::class, 'syncAgentStatuses'])->name('telemarketing.sync-agent-statuses');
            Route::post('/telemarketing/toggle-agent-active', [TelemarketingController::class, 'toggleAgentActive'])->name('telemarketing.toggle-agent-active');
            Route::post('/telemarketing/redistribute-agent', [TelemarketingController::class, 'redistributeAgent'])->name('telemarketing.redistribute-agent');
            Route::post('/telemarketing/rules', [TelemarketingController::class, 'storeRule'])->name('telemarketing.store-rule');
            Route::post('/telemarketing/rules/{rule}/toggle', [TelemarketingController::class, 'toggleRule'])->name('telemarketing.toggle-rule');
            Route::delete('/telemarketing/rules/{rule}', [TelemarketingController::class, 'deleteRule'])->name('telemarketing.delete-rule');
            Route::get('/telemarketing/dispositions', [TelemarketingController::class, 'dispositions'])->name('telemarketing.dispositions');
            Route::post('/telemarketing/dispositions', [TelemarketingController::class, 'storeDisposition'])->name('telemarketing.store-disposition');
            Route::delete('/telemarketing/dispositions/{disposition}', [TelemarketingController::class, 'deleteDisposition'])->name('telemarketing.delete-disposition');
            Route::get('/telemarketing/call-logs', [TelemarketingController::class, 'callLogs'])->name('telemarketing.call-logs');
            Route::post('/telemarketing/transition-rules', [TelemarketingController::class, 'storeTransitionRule'])->name('telemarketing.store-transition-rule');
            Route::post('/telemarketing/transition-rules/{transitionRule}/toggle', [TelemarketingController::class, 'toggleTransitionRule'])->name('telemarketing.toggle-transition-rule');
            Route::delete('/telemarketing/transition-rules/{transitionRule}', [TelemarketingController::class, 'deleteTransitionRule'])->name('telemarketing.delete-transition-rule');
        });

        // Recording playback (authenticated web route)
        Route::get('/telemarketing/recording/{log}', [RecordingController::class, 'play'])->name('telemarketing.play-recording');

        // Recording upload API (used by Android app, authenticated via session)
        Route::post('/api/telemarketing/upload-recording', [RecordingController::class, 'upload'])->name('api.telemarketing.upload-recording');

        // Create draft log when user clicks call button (used by Android app + WebView JS)
        Route::post('/api/telemarketing/create-draft-log', [RecordingController::class, 'createDraftLog'])->name('api.telemarketing.create-draft-log');
        Route::get("/api/telemarketing/call-history/{shipment}", [TelemarketingController::class, "callHistoryApi"])->name("api.telemarketing.call-history");

        // SMS Campaigns
        // IMPORTANT: 'create' route MUST come before '{campaign}' wildcard
        Route::middleware('company.can:sms.campaigns.create')->group(function () {
            Route::get('/sms/campaigns/create', [SmsCampaignController::class, 'create'])->name('sms.campaigns.create');
            Route::post('/sms/campaigns', [SmsCampaignController::class, 'store'])->name('sms.campaigns.store');
        });
        Route::middleware('company.can:sms.campaigns.view')->group(function () {
            Route::get('/sms/campaigns', [SmsCampaignController::class, 'index'])->name('sms.campaigns.index');
            Route::post('/sms/campaigns/preview-recipients', [SmsCampaignController::class, 'previewRecipients'])->name('sms.campaigns.preview-recipients');
            Route::get('/sms/campaigns/{campaign}', [SmsCampaignController::class, 'show'])->name('sms.campaigns.show');
            Route::get('/sms/campaigns/{campaign}/logs', [SmsCampaignController::class, 'logs'])->name('sms.campaigns.logs');
        });
        Route::middleware('company.can:sms.campaigns.edit')->group(function () {
            Route::get('/sms/campaigns/{campaign}/edit', [SmsCampaignController::class, 'edit'])->name('sms.campaigns.edit');
            Route::put('/sms/campaigns/{campaign}', [SmsCampaignController::class, 'update'])->name('sms.campaigns.update');
            Route::post('/sms/campaigns/{campaign}/start', [SmsCampaignController::class, 'start'])->name('sms.campaigns.start');
            Route::post('/sms/campaigns/{campaign}/pause', [SmsCampaignController::class, 'pause'])->name('sms.campaigns.pause');
            Route::post('/sms/campaigns/{campaign}/cancel', [SmsCampaignController::class, 'cancel'])->name('sms.campaigns.cancel');
            Route::post("/sms/campaigns/{campaign}/assign-operator", [SmsCampaignController::class, "assignOperator"])->name("sms.campaigns.assign-operator");
        });
        Route::middleware('company.can:sms.campaigns.toggle')->group(function () {
            Route::post('/sms/campaigns/{campaign}/toggle', [SmsCampaignController::class, 'toggle'])->name('sms.campaigns.toggle');
        });

        // SMS Devices
        Route::middleware('company.can:sms.campaigns.create')->group(function () {
            Route::get('/sms/devices', [SmsDeviceController::class, 'index'])->name('sms.devices.index');
            Route::post('/sms/devices', [SmsDeviceController::class, 'store'])->name('sms.devices.store');
            Route::post('/sms/devices/{device}/toggle', [SmsDeviceController::class, 'toggle'])->name('sms.devices.toggle');
            Route::post('/sms/devices/{device}/regenerate-token', [SmsDeviceController::class, 'regenerateToken'])->name('sms.devices.regenerate-token');
            Route::delete('/sms/devices/{device}', [SmsDeviceController::class, 'destroy'])->name('sms.devices.destroy');
        });

        // Remittance (CEO / Company Owner)
        Route::middleware('company.can:remittance.view')->group(function () {
            Route::get('/remittance', [RemittanceController::class, 'index'])->name('remittance.index');
        });

        // Company Settings (COD Fee Rate, etc.)
        Route::middleware('company.can:settings.manage')->group(function () {
            Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
            // Telemarketing Settings
            Route::get('/settings/telemarketing', [SettingsController::class, 'telemarketingSettings'])->name('settings.telemarketing');
            Route::put('/settings/telemarketing', [SettingsController::class, 'updateTelemarketingSettings'])->name('settings.telemarketing.update');
            Route::put('/settings/telemarketing/mapping', [SettingsController::class, 'updateDispositionMapping'])->name('settings.telemarketing.update-mapping');
            // Role Permissions
            Route::get('/settings/role-permissions', [SettingsController::class, 'rolePermissions'])->name('settings.role-permissions');
            Route::put('/settings/role-permissions', [SettingsController::class, 'updateRolePermissions'])->name('settings.role-permissions.update');
        });

        // Company User Management
        Route::middleware('company.can:users.view')->group(function () {
            Route::get('/company/users', [CompanyUserController::class, 'index'])->name('company.users.index');
        });
        Route::middleware('company.can:users.create')->group(function () {
            Route::get('/company/users/create', [CompanyUserController::class, 'create'])->name('company.users.create');
            Route::post('/company/users', [CompanyUserController::class, 'store'])->name('company.users.store');
        });
        Route::middleware('company.can:users.edit')->group(function () {
            Route::get('/company/users/{user}', function (\App\Models\User $user) {
                return redirect()->route('company.users.edit', $user);
            })->name('company.users.show');
            Route::get('/company/users/{user}/edit', [CompanyUserController::class, 'edit'])->name('company.users.edit');
            Route::put('/company/users/{user}', [CompanyUserController::class, 'update'])->name('company.users.update');
        });
        Route::middleware('company.can:users.toggle')->group(function () {
            Route::post('/company/users/{user}/toggle', [CompanyUserController::class, 'toggle'])->name('company.users.toggle');
        });
    });

    // ── Platform Admin Routes ──
    Route::middleware([\App\Http\Middleware\EnsurePlatformAdmin::class])->prefix('platform')->name('platform.')->group(function () {
        Route::get('/dashboard', [PlatformAdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/companies', [PlatformAdminController::class, 'companies'])->name('companies.index');
        Route::get('/companies/create', [PlatformAdminController::class, 'createCompany'])->name('companies.create');
        Route::post('/companies', [PlatformAdminController::class, 'storeCompany'])->name('companies.store');
        Route::get('/companies/{company}', [PlatformAdminController::class, 'showCompany'])->name('companies.show');
        Route::post('/companies/{company}/toggle', [PlatformAdminController::class, 'toggleCompany'])->name('companies.toggle');
        Route::put('/companies/{company}/users/{user}/password', [PlatformAdminController::class, 'updateUserPassword'])->name('companies.users.update-password');

        // Global role permissions management
        Route::get('/permissions', [PlatformAdminController::class, 'permissions'])->name('permissions');
        Route::put('/permissions', [PlatformAdminController::class, 'updatePermissions'])->name('permissions.update');
    });
});

// ── SMS Blast API (device-token auth, no session needed) ──
Route::prefix('api/sms-blast')->group(function () {
    Route::post('/auth', [\App\Http\Controllers\Api\SmsBlastApiController::class, 'authenticate']);
    Route::post('/heartbeat', [\App\Http\Controllers\Api\SmsBlastApiController::class, 'heartbeat']);
    Route::post('/pull', [\App\Http\Controllers\Api\SmsBlastApiController::class, 'pull']);
    Route::post('/report', [\App\Http\Controllers\Api\SmsBlastApiController::class, 'report']);
});

// ── SMS Blast Dashboard (for SMS Operators) ──
Route::middleware(["auth"])->group(function () {
    Route::get("/sms/blast", [\App\Http\Controllers\Sms\SmsBlastController::class, "dashboard"])->name("sms.blast.dashboard");
    Route::get("/sms/blast-status", [\App\Http\Controllers\Sms\SmsBlastController::class, "status"])->name("sms.blast.status");
    Route::post("/sms/blast-pull", [\App\Http\Controllers\Sms\SmsBlastController::class, "pull"])->name("sms.blast.pull");
    Route::post("/sms/blast-report", [\App\Http\Controllers\Sms\SmsBlastController::class, "report"])->name("sms.blast.report");
});
require __DIR__.'/auth.php';
