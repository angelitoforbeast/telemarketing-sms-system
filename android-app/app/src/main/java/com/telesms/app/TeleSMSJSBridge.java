package com.telesms.app;

import android.Manifest;
import android.app.Activity;
import android.app.role.RoleManager;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Build;
import android.os.Environment;
import android.provider.Settings;
import android.telecom.TelecomManager;
import android.util.Log;
import android.webkit.JavascriptInterface;

import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;

import org.json.JSONArray;
import org.json.JSONObject;

import java.util.List;

/**
 * JavaScript interface exposed to the WebView.
 * Allows the web app to:
 * - Trigger phone calls with call tracking
 * - Get call status and recording info
 * - Check/request permissions
 * - Get device info
 * - Check/request default dialer status
 */
public class TeleSMSJSBridge {

    private static final String TAG = "TeleSMSJSBridge";
    private static final int REQUEST_DEFAULT_DIALER = 200;

    private final Activity activity;

    public TeleSMSJSBridge(Activity activity) {
        this.activity = activity;
    }

    /**
     * Make a phone call and track it for recording.
     * Called from WebView when telemarketer clicks the Call button.
     * Now also sets call context for CallManager (used by InCallService).
     *
     * @param phoneNumber  The phone number to call
     * @param shipmentId   The shipment ID for this call
     * @param logId        The telemarketing log ID (if already created)
     */
    @JavascriptInterface
    public void makeCall(String phoneNumber, String shipmentId, String logId) {
        Log.i(TAG, "makeCall: " + phoneNumber + " shipment: " + shipmentId + " log: " + logId);

        // Store call context for both PhoneCallReceiver and CallManager
        SharedPreferences prefs = activity.getSharedPreferences("telesms_call", Context.MODE_PRIVATE);
        prefs.edit()
                .putString("current_phone_number", phoneNumber)
                .putString("current_shipment_id", shipmentId)
                .putString("current_log_id", logId)
                .putLong("call_initiated_time", System.currentTimeMillis())
                .putBoolean("call_active", false)
                .putBoolean("pending_recording_upload", false)
                .apply();

        // Also set context in CallManager singleton (for InCallService)
        CallManager.getInstance().setCallContext(phoneNumber, shipmentId, logId);

        // Initiate the phone call
        try {
            Intent callIntent = new Intent(Intent.ACTION_CALL);
            callIntent.setData(Uri.parse("tel:" + phoneNumber));

            if (ContextCompat.checkSelfPermission(activity, Manifest.permission.CALL_PHONE)
                    == PackageManager.PERMISSION_GRANTED) {
                activity.startActivity(callIntent);
            } else {
                // Fall back to dial intent (user has to press call button)
                Intent dialIntent = new Intent(Intent.ACTION_DIAL);
                dialIntent.setData(Uri.parse("tel:" + phoneNumber));
                activity.startActivity(dialIntent);
            }
        } catch (Exception e) {
            Log.e(TAG, "Failed to make call: " + e.getMessage());
        }
    }

    /**
     * Get the status of the last call (duration, recording status).
     * Called from WebView to update the UI after returning from a call.
     * Now includes recording audio source info.
     *
     * @return JSON string with call status info
     */
    @JavascriptInterface
    public String getCallStatus() {
        SharedPreferences prefs = activity.getSharedPreferences("telesms_call", Context.MODE_PRIVATE);

        try {
            JSONObject status = new JSONObject();
            status.put("callActive", prefs.getBoolean("call_active", false));
            status.put("callDuration", prefs.getInt("last_call_duration", 0));
            status.put("callStartTime", prefs.getLong("call_start_time", 0));
            status.put("callEndTime", prefs.getLong("call_end_time", 0));
            status.put("pendingUpload", prefs.getBoolean("pending_recording_upload", false));
            status.put("lastUploadSuccess", prefs.getBoolean("last_upload_success", false));
            status.put("lastRecordingPath", prefs.getString("last_recording_path", ""));
            status.put("phoneNumber", prefs.getString("current_phone_number", ""));
            status.put("shipmentId", prefs.getString("current_shipment_id", ""));
            status.put("recordingAudioSource", prefs.getString("recording_audio_source", ""));
            status.put("isDefaultDialer", isDefaultDialer());
            return status.toString();
        } catch (Exception e) {
            return "{}";
        }
    }

    /**
     * Store the auth cookie from the WebView session for API uploads.
     */
    @JavascriptInterface
    public void setAuthCookie(String cookie) {
        SharedPreferences prefs = activity.getSharedPreferences("telesms_call", Context.MODE_PRIVATE);
        prefs.edit().putString("auth_cookie", cookie).apply();
        Log.d(TAG, "Auth cookie stored");
    }

    /**
     * Store the API token for authenticated uploads.
     */
    @JavascriptInterface
    public void setApiToken(String token) {
        SharedPreferences prefs = activity.getSharedPreferences("telesms", Context.MODE_PRIVATE);
        prefs.edit().putString("api_token", token).apply();
        Log.d(TAG, "API token stored");
    }

    /**
     * Store user credentials for API token auto-login.
     * Called from injected JS when user submits the login form.
     */
    @JavascriptInterface
    public void storeCredentials(String email, String password) {
        SharedPreferences prefs = activity.getSharedPreferences("telesms", Context.MODE_PRIVATE);
        prefs.edit()
                .putString("user_email", email)
                .putString("user_password", password)
                .apply();
        Log.d(TAG, "User credentials stored for API auth");

        // Immediately try to get an API token in background
        new Thread(() -> {
            try {
                RecordingUploader uploader = new RecordingUploader(activity);
                // The uploader will auto-login and store the token
                // We trigger this by calling a dummy method that initializes auth
                String serverUrl = prefs.getString("server_url", "http://76.13.215.149");
                String loginUrl = serverUrl + "/api/mobile/login";

                org.json.JSONObject body = new org.json.JSONObject();
                body.put("email", email);
                body.put("password", password);

                okhttp3.RequestBody requestBody = okhttp3.RequestBody.create(
                        body.toString(),
                        okhttp3.MediaType.parse("application/json")
                );

                okhttp3.OkHttpClient client = new okhttp3.OkHttpClient();
                okhttp3.Request request = new okhttp3.Request.Builder()
                        .url(loginUrl)
                        .post(requestBody)
                        .addHeader("Accept", "application/json")
                        .addHeader("Content-Type", "application/json")
                        .build();

                okhttp3.Response response = client.newCall(request).execute();
                if (response.isSuccessful()) {
                    String responseBody = response.body() != null ? response.body().string() : "";
                    org.json.JSONObject json = new org.json.JSONObject(responseBody);
                    if (json.optBoolean("success", false)) {
                        String token = json.getString("token");
                        prefs.edit().putString("api_token", token).apply();
                        Log.i(TAG, "API token obtained via auto-login after credential store");
                    }
                }
            } catch (Exception e) {
                Log.e(TAG, "Failed to get API token after credential store: " + e.getMessage());
            }
        }).start();
    }

    /**
     * Refresh the API token if credentials are stored.
     * Called from injected JS when user is already logged in.
     */
    @JavascriptInterface
    public void refreshApiToken() {
        SharedPreferences prefs = activity.getSharedPreferences("telesms", Context.MODE_PRIVATE);
        String existingToken = prefs.getString("api_token", "");

        // Only refresh if no token exists
        if (!existingToken.isEmpty()) {
            Log.d(TAG, "API token already exists, skipping refresh");
            return;
        }

        String email = prefs.getString("user_email", "");
        String password = prefs.getString("user_password", "");

        if (email.isEmpty() || password.isEmpty()) {
            Log.d(TAG, "No stored credentials for API token refresh");
            return;
        }

        // Get token in background
        new Thread(() -> {
            try {
                String serverUrl = prefs.getString("server_url", "http://76.13.215.149");
                String loginUrl = serverUrl + "/api/mobile/login";

                org.json.JSONObject body = new org.json.JSONObject();
                body.put("email", email);
                body.put("password", password);

                okhttp3.RequestBody requestBody = okhttp3.RequestBody.create(
                        body.toString(),
                        okhttp3.MediaType.parse("application/json")
                );

                okhttp3.OkHttpClient client = new okhttp3.OkHttpClient();
                okhttp3.Request request = new okhttp3.Request.Builder()
                        .url(loginUrl)
                        .post(requestBody)
                        .addHeader("Accept", "application/json")
                        .addHeader("Content-Type", "application/json")
                        .build();

                okhttp3.Response response = client.newCall(request).execute();
                if (response.isSuccessful()) {
                    String responseBody = response.body() != null ? response.body().string() : "";
                    org.json.JSONObject json = new org.json.JSONObject(responseBody);
                    if (json.optBoolean("success", false)) {
                        String token = json.getString("token");
                        prefs.edit().putString("api_token", token).apply();
                        Log.i(TAG, "API token refreshed successfully");
                    }
                }
            } catch (Exception e) {
                Log.e(TAG, "Failed to refresh API token: " + e.getMessage());
            }
        }).start();
    }

    /**
     * Check if all required permissions are granted.
     * Now includes RECORD_AUDIO and default dialer status.
     *
     * @return JSON string with permission status
     */
    @JavascriptInterface
    public String checkPermissions() {
        try {
            JSONObject perms = new JSONObject();
            perms.put("callPhone", hasPermission(Manifest.permission.CALL_PHONE));
            perms.put("readPhoneState", hasPermission(Manifest.permission.READ_PHONE_STATE));
            perms.put("readCallLog", hasPermission(Manifest.permission.READ_CALL_LOG));
            perms.put("recordAudio", hasPermission(Manifest.permission.RECORD_AUDIO));
            perms.put("readStorage", hasStoragePermission());
            perms.put("notifications", hasPermission(Manifest.permission.POST_NOTIFICATIONS));
            perms.put("isDefaultDialer", isDefaultDialer());
            perms.put("allGranted",
                    hasPermission(Manifest.permission.CALL_PHONE) &&
                    hasPermission(Manifest.permission.READ_PHONE_STATE) &&
                    hasPermission(Manifest.permission.READ_CALL_LOG) &&
                    hasPermission(Manifest.permission.RECORD_AUDIO) &&
                    hasStoragePermission());
            return perms.toString();
        } catch (Exception e) {
            return "{}";
        }
    }

    /**
     * Request all required permissions including RECORD_AUDIO.
     */
    @JavascriptInterface
    public void requestPermissions() {
        activity.runOnUiThread(() -> {
            String[] permissions = {
                    Manifest.permission.CALL_PHONE,
                    Manifest.permission.READ_PHONE_STATE,
                    Manifest.permission.READ_CALL_LOG,
                    Manifest.permission.RECORD_AUDIO,
                    Manifest.permission.POST_NOTIFICATIONS
            };
            ActivityCompat.requestPermissions(activity, permissions, 100);
        });
    }

    /**
     * Request storage permission (special handling for Android 11+).
     */
    @JavascriptInterface
    public void requestStoragePermission() {
        activity.runOnUiThread(() -> {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
                // Android 11+ needs MANAGE_EXTERNAL_STORAGE
                if (!Environment.isExternalStorageManager()) {
                    try {
                        Intent intent = new Intent(Settings.ACTION_MANAGE_APP_ALL_FILES_ACCESS_PERMISSION);
                        intent.setData(Uri.parse("package:" + activity.getPackageName()));
                        activity.startActivity(intent);
                    } catch (Exception e) {
                        Intent intent = new Intent(Settings.ACTION_MANAGE_ALL_FILES_ACCESS_PERMISSION);
                        activity.startActivity(intent);
                    }
                }
            } else {
                ActivityCompat.requestPermissions(activity,
                        new String[]{Manifest.permission.READ_EXTERNAL_STORAGE},
                        101);
            }
        });
    }

    // ==================== Default Dialer ====================

    /**
     * Check if TeleSMS is the default dialer/phone app.
     */
    @JavascriptInterface
    public boolean isDefaultDialer() {
        try {
            TelecomManager telecomManager = (TelecomManager) activity.getSystemService(Context.TELECOM_SERVICE);
            if (telecomManager != null) {
                String defaultDialer = telecomManager.getDefaultDialerPackage();
                return activity.getPackageName().equals(defaultDialer);
            }
        } catch (Exception e) {
            Log.e(TAG, "Error checking default dialer: " + e.getMessage());
        }
        return false;
    }

    /**
     * Request to become the default dialer.
     * Shows system dialog asking user to set TeleSMS as default phone app.
     */
    @JavascriptInterface
    public void requestDefaultDialer() {
        activity.runOnUiThread(() -> {
            try {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                    // Android 10+ uses RoleManager
                    RoleManager roleManager = activity.getSystemService(RoleManager.class);
                    if (roleManager != null && roleManager.isRoleAvailable(RoleManager.ROLE_DIALER)
                            && !roleManager.isRoleHeld(RoleManager.ROLE_DIALER)) {
                        Intent intent = roleManager.createRequestRoleIntent(RoleManager.ROLE_DIALER);
                        activity.startActivityForResult(intent, REQUEST_DEFAULT_DIALER);
                    }
                } else {
                    // Android 7-9 uses TelecomManager
                    Intent intent = new Intent(TelecomManager.ACTION_CHANGE_DEFAULT_DIALER);
                    intent.putExtra(TelecomManager.EXTRA_CHANGE_DEFAULT_DIALER_PACKAGE_NAME,
                            activity.getPackageName());
                    activity.startActivityForResult(intent, REQUEST_DEFAULT_DIALER);
                }
            } catch (Exception e) {
                Log.e(TAG, "Error requesting default dialer: " + e.getMessage());
            }
        });
    }

    /**
     * Get detected recording directories on this device.
     */
    @JavascriptInterface
    public String getRecordingPaths() {
        try {
            List<String> paths = RecordingFinder.getActiveRecordingPaths();
            JSONArray arr = new JSONArray();
            for (String path : paths) {
                arr.put(path);
            }
            return arr.toString();
        } catch (Exception e) {
            return "[]";
        }
    }

    /**
     * Get the server URL.
     */
    @JavascriptInterface
    public String getServerUrl() {
        SharedPreferences prefs = activity.getSharedPreferences("telesms", Context.MODE_PRIVATE);
        return prefs.getString("server_url", "");
    }

    /**
     * Change the server URL.
     */
    @JavascriptInterface
    public void setServerUrl(String url) {
        SharedPreferences prefs = activity.getSharedPreferences("telesms", Context.MODE_PRIVATE);
        prefs.edit().putString("server_url", url).apply();
    }

    /**
     * Get app version info. Now includes default dialer status.
     */
    @JavascriptInterface
    public String getAppInfo() {
        try {
            JSONObject info = new JSONObject();
            info.put("appName", "TeleSMS");
            info.put("version", "2.0.0");
            info.put("platform", "android");
            info.put("sdkVersion", Build.VERSION.SDK_INT);
            info.put("device", Build.MANUFACTURER + " " + Build.MODEL);
            info.put("isDefaultDialer", isDefaultDialer());
            info.put("hasRecordAudio", hasPermission(Manifest.permission.RECORD_AUDIO));
            return info.toString();
        } catch (Exception e) {
            return "{}";
        }
    }

    /**
     * Check if running inside the TeleSMS app (for web app detection).
     */
    @JavascriptInterface
    public boolean isApp() {
        return true;
    }

    private boolean hasPermission(String permission) {
        return ContextCompat.checkSelfPermission(activity, permission)
                == PackageManager.PERMISSION_GRANTED;
    }

    private boolean hasStoragePermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            return Environment.isExternalStorageManager();
        } else {
            return hasPermission(Manifest.permission.READ_EXTERNAL_STORAGE);
        }
    }
}
