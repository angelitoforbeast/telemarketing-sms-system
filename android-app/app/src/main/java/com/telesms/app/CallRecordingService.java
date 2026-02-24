package com.telesms.app;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.Service;
import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Build;
import android.os.Handler;
import android.os.IBinder;
import android.os.Looper;
import android.util.Log;

import androidx.annotation.Nullable;
import androidx.core.app.NotificationCompat;

import java.io.File;

/**
 * Foreground service that finds the latest call recording after a call ends
 * and uploads it to the server. Runs with a small delay to give the phone
 * time to finish writing the recording file.
 */
public class CallRecordingService extends Service {

    private static final String TAG = "CallRecordingService";
    private static final String CHANNEL_ID = "call_recording_channel";
    private static final int NOTIFICATION_ID = 1001;

    // Delay before scanning for recording (phone needs time to save the file)
    private static final long SCAN_DELAY_MS = 3000; // 3 seconds
    private static final int MAX_RETRIES = 5;
    private static final long RETRY_DELAY_MS = 2000; // 2 seconds between retries

    private Handler handler;
    private RecordingUploader uploader;

    @Override
    public void onCreate() {
        super.onCreate();
        handler = new Handler(Looper.getMainLooper());
        uploader = new RecordingUploader(this);
        createNotificationChannel();
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        // Start as foreground service
        Notification notification = new NotificationCompat.Builder(this, CHANNEL_ID)
                .setContentTitle("TeleSMS")
                .setContentText("Processing call recording...")
                .setSmallIcon(android.R.drawable.ic_menu_upload)
                .setPriority(NotificationCompat.PRIORITY_LOW)
                .setOngoing(true)
                .build();

        startForeground(NOTIFICATION_ID, notification);

        if (intent != null && "FIND_AND_UPLOAD".equals(intent.getAction())) {
            long callStartTime = intent.getLongExtra("call_start_time", 0);
            long callEndTime = intent.getLongExtra("call_end_time", System.currentTimeMillis());
            int callDuration = intent.getIntExtra("call_duration", 0);
            String phoneNumber = intent.getStringExtra("phone_number");
            String shipmentId = intent.getStringExtra("shipment_id");
            String logId = intent.getStringExtra("log_id");

            Log.i(TAG, "Will scan for recording in " + SCAN_DELAY_MS + "ms...");

            // Delay to let the phone finish writing the recording file
            handler.postDelayed(() -> {
                findAndUploadWithRetry(callStartTime, phoneNumber, shipmentId,
                        logId, callDuration, 0);
            }, SCAN_DELAY_MS);
        } else {
            stopSelf();
        }

        return START_NOT_STICKY;
    }

    private void findAndUploadWithRetry(long callStartTime, String phoneNumber,
                                         String shipmentId, String logId,
                                         int callDuration, int attempt) {
        Log.i(TAG, "Scanning for recording (attempt " + (attempt + 1) + "/" + MAX_RETRIES + ")...");

        File recording = RecordingFinder.findLatestRecording(callStartTime, phoneNumber);

        if (recording != null && recording.exists()) {
            Log.i(TAG, "Found recording: " + recording.getAbsolutePath());

            // Update notification
            updateNotification("Uploading recording...");

            // Upload in background thread
            new Thread(() -> {
                SharedPreferences prefs = getSharedPreferences("telesms_call", MODE_PRIVATE);
                String authCookie = prefs.getString("auth_cookie", "");

                boolean success = uploader.upload(recording, shipmentId, logId,
                        phoneNumber, callDuration, authCookie);

                if (success) {
                    Log.i(TAG, "Recording uploaded successfully!");
                    updateNotification("Recording uploaded ✓");

                    // Store result for the WebView to pick up
                    prefs.edit()
                            .putBoolean("pending_recording_upload", false)
                            .putString("last_recording_path", recording.getAbsolutePath())
                            .putBoolean("last_upload_success", true)
                            .apply();
                } else {
                    Log.e(TAG, "Recording upload failed");
                    updateNotification("Upload failed — will retry later");

                    // Store for retry
                    prefs.edit()
                            .putString("pending_upload_path", recording.getAbsolutePath())
                            .putString("pending_upload_shipment_id", shipmentId)
                            .putString("pending_upload_log_id", logId)
                            .putString("pending_upload_phone", phoneNumber)
                            .putInt("pending_upload_duration", callDuration)
                            .apply();
                }

                // Stop service after a short delay
                handler.postDelayed(this::stopSelf, 2000);
            }).start();

        } else if (attempt < MAX_RETRIES - 1) {
            // Retry — recording might not be saved yet
            Log.d(TAG, "No recording found yet, retrying in " + RETRY_DELAY_MS + "ms...");
            handler.postDelayed(() -> {
                findAndUploadWithRetry(callStartTime, phoneNumber, shipmentId,
                        logId, callDuration, attempt + 1);
            }, RETRY_DELAY_MS);

        } else {
            Log.w(TAG, "No recording found after " + MAX_RETRIES + " attempts");
            updateNotification("No recording found");

            SharedPreferences prefs = getSharedPreferences("telesms_call", MODE_PRIVATE);
            prefs.edit()
                    .putBoolean("pending_recording_upload", false)
                    .putBoolean("last_upload_success", false)
                    .putString("last_recording_path", "")
                    .apply();

            handler.postDelayed(this::stopSelf, 2000);
        }
    }

    private void updateNotification(String text) {
        NotificationManager manager = getSystemService(NotificationManager.class);
        if (manager != null) {
            Notification notification = new NotificationCompat.Builder(this, CHANNEL_ID)
                    .setContentTitle("TeleSMS")
                    .setContentText(text)
                    .setSmallIcon(android.R.drawable.ic_menu_upload)
                    .setPriority(NotificationCompat.PRIORITY_LOW)
                    .build();
            manager.notify(NOTIFICATION_ID, notification);
        }
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                    CHANNEL_ID,
                    getString(R.string.channel_name),
                    NotificationManager.IMPORTANCE_LOW
            );
            channel.setDescription(getString(R.string.channel_description));

            NotificationManager manager = getSystemService(NotificationManager.class);
            if (manager != null) {
                manager.createNotificationChannel(channel);
            }
        }
    }

    @Nullable
    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }
}
