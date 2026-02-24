package com.telesms.app;

import android.Manifest;
import android.app.Activity;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Environment;
import android.provider.Settings;
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
 */
public class TeleSMSJSBridge {

    private static final String TAG = "TeleSMSJSBridge";
    private final Activity activity;

    public TeleSMSJSBridge(Activity activity) {
        this.activity = activity;
    }

    /**
     * Make a phone call and track it for recording.
     * Called from WebView when telemarketer clicks the Call button.
     *
     * @param phoneNumber  The phone number to call
     * @param shipmentId   The shipment ID for this call
     * @param logId        The telemarketing log ID (if already created)
     */
    @JavascriptInterface
    public void makeCall(String phoneNumber, String shipmentId, String logId) {
        Log.i(TAG, "makeCall: " + phoneNumber + " shipment: " + shipmentId + " log: " + logId);

        // Store call context for the PhoneCallReceiver
        SharedPreferences prefs = activity.getSharedPreferences("telesms_call", Context.MODE_PRIVATE);
        prefs.edit()
                .putString("current_phone_number", phoneNumber)
                .putString("current_shipment_id", shipmentId)
                .putString("current_log_id", logId)
                .putLong("call_initiated_time", System.currentTimeMillis())
                .putBoolean("call_active", false)
                .putBoolean("pending_recording_upload", false)
                .apply();

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
     * Check if all required permissions are granted.
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
            perms.put("readStorage", hasStoragePermission());
            perms.put("notifications", hasPermission(Manifest.permission.POST_NOTIFICATIONS));
            perms.put("allGranted",
                    hasPermission(Manifest.permission.CALL_PHONE) &&
                    hasPermission(Manifest.permission.READ_PHONE_STATE) &&
                    hasPermission(Manifest.permission.READ_CALL_LOG) &&
                    hasStoragePermission());
            return perms.toString();
        } catch (Exception e) {
            return "{}";
        }
    }

    /**
     * Request all required permissions.
     */
    @JavascriptInterface
    public void requestPermissions() {
        activity.runOnUiThread(() -> {
            String[] permissions = {
                    Manifest.permission.CALL_PHONE,
                    Manifest.permission.READ_PHONE_STATE,
                    Manifest.permission.READ_CALL_LOG,
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
            if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.R) {
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
     * Get app version info.
     */
    @JavascriptInterface
    public String getAppInfo() {
        try {
            JSONObject info = new JSONObject();
            info.put("appName", "TeleSMS");
            info.put("version", "1.0.0");
            info.put("platform", "android");
            info.put("sdkVersion", android.os.Build.VERSION.SDK_INT);
            info.put("device", android.os.Build.MANUFACTURER + " " + android.os.Build.MODEL);
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
        if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.R) {
            return Environment.isExternalStorageManager();
        } else {
            return hasPermission(Manifest.permission.READ_EXTERNAL_STORAGE);
        }
    }
}
