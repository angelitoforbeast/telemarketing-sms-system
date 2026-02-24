package com.telesms.app;

import android.content.Context;
import android.content.SharedPreferences;
import android.util.Log;

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
     * Upload a recording file to the server.
     *
     * @param file           The recording audio file
     * @param shipmentId     The shipment ID this call was for
     * @param logId          The telemarketing log ID
     * @param phoneNumber    The phone number that was called
     * @param callDuration   Call duration in seconds
     * @param authToken      The user's auth token/cookie
     * @return true if upload was successful
     */
    public boolean upload(File file, String shipmentId, String logId,
                          String phoneNumber, int callDuration, String authToken) {
        SharedPreferences prefs = context.getSharedPreferences("telesms", Context.MODE_PRIVATE);
        String serverUrl = prefs.getString("server_url", "");

        if (serverUrl.isEmpty()) {
            Log.e(TAG, "No server URL configured");
            return false;
        }

        String uploadUrl = serverUrl + "/api/telemarketing/upload-recording";

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

            // Add auth token if available
            if (authToken != null && !authToken.isEmpty()) {
                requestBuilder.addHeader("Cookie", authToken);
            }

            // Also try with API token from prefs
            String apiToken = prefs.getString("api_token", "");
            if (!apiToken.isEmpty()) {
                requestBuilder.addHeader("Authorization", "Bearer " + apiToken);
            }

            Request request = requestBuilder.build();

            Log.i(TAG, "Uploading recording: " + file.getName() +
                    " (" + file.length() + " bytes) to " + uploadUrl);

            Response response = client.newCall(request).execute();

            if (response.isSuccessful()) {
                String body = response.body() != null ? response.body().string() : "";
                Log.i(TAG, "Upload successful: " + body);

                // Mark as uploaded in prefs to avoid re-upload
                prefs.edit().putString("last_uploaded_" + file.getAbsolutePath(),
                        String.valueOf(System.currentTimeMillis())).apply();

                return true;
            } else {
                String body = response.body() != null ? response.body().string() : "";
                Log.e(TAG, "Upload failed: HTTP " + response.code() + " - " + body);
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
}
