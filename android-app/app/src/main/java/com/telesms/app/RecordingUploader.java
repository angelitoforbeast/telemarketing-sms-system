package com.telesms.app;

import android.content.Context;
import android.content.SharedPreferences;
import android.util.Log;
import android.webkit.CookieManager;

import org.json.JSONObject;

import java.io.File;
import java.io.IOException;
import java.util.concurrent.TimeUnit;

import okhttp3.MediaType;
import okhttp3.MultipartBody;
import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.RequestBody;
import okhttp3.Response;

/**
 * Uploads call recording files to the Laravel server via API.
 * Uses Sanctum API token for authentication (Bearer token).
 * Falls back to WebView cookie sync if no API token is available.
 */
public class RecordingUploader {

    private static final String TAG = "RecordingUploader";

    private final Context context;
    private final OkHttpClient client;

    public RecordingUploader(Context context) {
        this.context = context;
        this.client = new OkHttpClient.Builder()
                .connectTimeout(30, TimeUnit.SECONDS)
                .writeTimeout(120, TimeUnit.SECONDS)
                .readTimeout(30, TimeUnit.SECONDS)
                .build();
    }

    /**
     * Get the server URL from SharedPreferences.
     */
    private String getServerUrl() {
        SharedPreferences prefs = context.getSharedPreferences("telesms", Context.MODE_PRIVATE);
        String url = prefs.getString("server_url", "");
        if (url.isEmpty()) {
            // Fallback to hardcoded URL
            url = "http://76.13.215.149";
        }
        return url;
    }

    /**
     * Get the Sanctum API token, refreshing if needed.
     * Priority: stored API token > WebView cookie sync > auto-login
     */
    private String getAuthToken() {
        SharedPreferences prefs = context.getSharedPreferences("telesms", Context.MODE_PRIVATE);

        // 1. Check for stored Sanctum API token
        String apiToken = prefs.getString("api_token", "");
        if (!apiToken.isEmpty()) {
            return apiToken;
        }

        // 2. Try to get token via auto-login using stored credentials
        String email = prefs.getString("user_email", "");
        String password = prefs.getString("user_password", "");
        if (!email.isEmpty() && !password.isEmpty()) {
            String newToken = loginAndGetToken(email, password);
            if (newToken != null) {
                prefs.edit().putString("api_token", newToken).apply();
                return newToken;
            }
        }

        return "";
    }

    /**
     * Login to the server and get a Sanctum API token.
     */
    private String loginAndGetToken(String email, String password) {
        String serverUrl = getServerUrl();
        String loginUrl = serverUrl + "/api/mobile/login";

        try {
            JSONObject body = new JSONObject();
            body.put("email", email);
            body.put("password", password);

            RequestBody requestBody = RequestBody.create(
                    body.toString(),
                    MediaType.parse("application/json")
            );

            Request request = new Request.Builder()
                    .url(loginUrl)
                    .post(requestBody)
                    .addHeader("Accept", "application/json")
                    .addHeader("Content-Type", "application/json")
                    .build();

            Response response = client.newCall(request).execute();
            if (response.isSuccessful()) {
                String responseBody = response.body() != null ? response.body().string() : "";
                JSONObject json = new JSONObject(responseBody);
                if (json.optBoolean("success", false)) {
                    String token = json.getString("token");
                    Log.i(TAG, "Auto-login successful, got API token");
                    return token;
                }
            } else {
                Log.w(TAG, "Auto-login failed: HTTP " + response.code());
            }
        } catch (Exception e) {
            Log.e(TAG, "Auto-login error: " + e.getMessage(), e);
        }
        return null;
    }

    /**
     * Get cookies from WebView CookieManager for the server URL.
     */
    private String getWebViewCookies() {
        try {
            String serverUrl = getServerUrl();
            CookieManager cookieManager = CookieManager.getInstance();
            String cookies = cookieManager.getCookie(serverUrl);
            if (cookies != null && !cookies.isEmpty()) {
                Log.d(TAG, "Got WebView cookies: " + cookies.substring(0, Math.min(50, cookies.length())) + "...");
                return cookies;
            }
        } catch (Exception e) {
            Log.w(TAG, "Failed to get WebView cookies: " + e.getMessage());
        }
        return "";
    }

    /**
     * Upload a recording file to the server.
     * Uses the new /api/mobile/upload-recording endpoint with Sanctum token.
     * Falls back to /api/telemarketing/upload-recording with cookie auth.
     */
    public boolean upload(File file, String shipmentId, String logId,
                          String phoneNumber, int callDuration, String authToken) {
        String serverUrl = getServerUrl();

        if (serverUrl.isEmpty()) {
            Log.e(TAG, "No server URL configured");
            return false;
        }

        // Get the best available auth
        String apiToken = getAuthToken();
        String webViewCookies = getWebViewCookies();

        // Choose endpoint based on auth method
        String uploadUrl;
        if (!apiToken.isEmpty()) {
            uploadUrl = serverUrl + "/api/mobile/upload-recording";
        } else {
            uploadUrl = serverUrl + "/api/telemarketing/upload-recording";
        }

        try {
            // Determine media type from file extension
            String fileName = file.getName().toLowerCase();
            String mediaType = "audio/mpeg"; // default
            if (fileName.endsWith(".m4a")) mediaType = "audio/mp4";
            else if (fileName.endsWith(".amr")) mediaType = "audio/amr";
            else if (fileName.endsWith(".wav")) mediaType = "audio/wav";
            else if (fileName.endsWith(".ogg") || fileName.endsWith(".opus")) mediaType = "audio/ogg";
            else if (fileName.endsWith(".3gp")) mediaType = "audio/3gpp";
            else if (fileName.endsWith(".aac")) mediaType = "audio/aac";

            RequestBody fileBody = RequestBody.create(file, MediaType.parse(mediaType));

            MultipartBody requestBody = new MultipartBody.Builder()
                    .setType(MultipartBody.FORM)
                    .addFormDataPart("recording", file.getName(), fileBody)
                    .addFormDataPart("shipment_id", shipmentId != null ? shipmentId : "")
                    .addFormDataPart("log_id", logId != null ? logId : "")
                    .addFormDataPart("phone_number", phoneNumber != null ? phoneNumber : "")
                    .addFormDataPart("call_duration", String.valueOf(callDuration))
                    .build();

            Request.Builder requestBuilder = new Request.Builder()
                    .url(uploadUrl)
                    .post(requestBody)
                    .addHeader("Accept", "application/json");

            // Add Sanctum Bearer token (primary auth method)
            if (!apiToken.isEmpty()) {
                requestBuilder.addHeader("Authorization", "Bearer " + apiToken);
                Log.d(TAG, "Using Sanctum API token for auth");
            }

            // Also add WebView cookies as fallback
            if (!webViewCookies.isEmpty()) {
                requestBuilder.addHeader("Cookie", webViewCookies);
                Log.d(TAG, "Also sending WebView cookies for auth");
            }

            // Legacy cookie from SharedPreferences
            if (authToken != null && !authToken.isEmpty()) {
                if (webViewCookies.isEmpty()) {
                    requestBuilder.addHeader("Cookie", authToken);
                }
            }

            Request request = requestBuilder.build();

            Log.i(TAG, "Uploading recording: " + file.getName() +
                    " (" + file.length() + " bytes) to " + uploadUrl);

            Response response = client.newCall(request).execute();

            if (response.isSuccessful()) {
                String body = response.body() != null ? response.body().string() : "";
                Log.i(TAG, "Upload successful: " + body);

                // Mark as uploaded in prefs to avoid re-upload
                SharedPreferences prefs = context.getSharedPreferences("telesms", Context.MODE_PRIVATE);
                prefs.edit().putString("last_uploaded_" + file.getAbsolutePath(),
                        String.valueOf(System.currentTimeMillis())).apply();

                return true;
            } else {
                String body = response.body() != null ? response.body().string() : "";
                Log.e(TAG, "Upload failed: HTTP " + response.code() + " - " + body);

                // If 401, try to refresh token and retry once
                if (response.code() == 401 && !apiToken.isEmpty()) {
                    Log.i(TAG, "Token may be expired, clearing and will retry with fresh token");
                    SharedPreferences prefs = context.getSharedPreferences("telesms", Context.MODE_PRIVATE);
                    prefs.edit().remove("api_token").apply();
                }

                return false;
            }

        } catch (IOException e) {
            Log.e(TAG, "Upload error: " + e.getMessage(), e);
            return false;
        }
    }

    /**
     * Check if a file was already uploaded.
     */
    public boolean isAlreadyUploaded(File file) {
        SharedPreferences prefs = context.getSharedPreferences("telesms", Context.MODE_PRIVATE);
        return prefs.contains("last_uploaded_" + file.getAbsolutePath());
    }

    /**
     * Convenience method for uploading from InCallService.
     * Reads auth and duration from SharedPreferences.
     */
    public boolean uploadRecording(File file, String phoneNumber,
                                    String shipmentId, String logId) {
        SharedPreferences prefs = context.getSharedPreferences("telesms_call", Context.MODE_PRIVATE);
        String authToken = prefs.getString("auth_cookie", "");
        int callDuration = prefs.getInt("last_call_duration", 0);
        return upload(file, shipmentId, logId, phoneNumber, callDuration, authToken);
    }

    /**
     * Upload with retry and exponential backoff.
     * On 401 errors, clears token so next retry will auto-login fresh.
     */
    public boolean uploadWithRetry(File file, String phoneNumber,
                                    String shipmentId, String logId, int maxRetries) {
        for (int attempt = 0; attempt <= maxRetries; attempt++) {
            if (uploadRecording(file, phoneNumber, shipmentId, logId)) {
                return true;
            }
            if (attempt < maxRetries) {
                long delay = (long) Math.pow(2, attempt) * 2000; // 2s, 4s, 8s
                Log.w(TAG, "Upload failed, retrying in " + delay + "ms (attempt "
                        + (attempt + 1) + "/" + maxRetries + ")");
                try {
                    Thread.sleep(delay);
                } catch (InterruptedException e) {
                    Thread.currentThread().interrupt();
                    return false;
                }
            }
        }
        return false;
    }
}
