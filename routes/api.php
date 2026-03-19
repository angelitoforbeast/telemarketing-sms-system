<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// ============================================================
// Mobile App API Routes (Sanctum token-based auth)
// ============================================================

// Login endpoint - returns API token for the mobile app
Route::post('/mobile/login', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json(['error' => 'Invalid credentials'], 401);
    }

    // Revoke old tokens for this device
    $user->tokens()->where('name', 'telesms-android')->delete();

    // Create new token
    $token = $user->createToken('telesms-android');

    return response()->json([
        'success' => true,
        'token' => $token->plainTextToken,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ],
    ]);
});

// Protected mobile API routes
Route::middleware('auth:sanctum')->group(function () {
    // Recording upload via API token
    Route::post('/mobile/upload-recording', [\App\Http\Controllers\Api\RecordingController::class, 'upload'])
        ->name('api.mobile.upload-recording');

    // Create draft log via API token
    Route::post('/mobile/create-draft-log', [\App\Http\Controllers\Api\RecordingController::class, 'createDraftLog'])
        ->name('api.mobile.create-draft-log');

    // Check recording via API token
    Route::get('/mobile/check-recording/{shipment}', [\App\Http\Controllers\Api\RecordingController::class, 'checkRecording'])
        ->name('api.mobile.check-recording');
});
