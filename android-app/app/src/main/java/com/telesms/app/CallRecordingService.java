package com.telesms.app;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.Service;
import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Build;
import android.os.Handler;
import android.os.HandlerThread;
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
 *
 * All file scanning and uploading runs on a dedicated background thread
 * to avoid blocking the main/UI thread.
 */
public class CallRecordingService extends Service {

    private static final String TAG = "CallRecordingService";
    private static final String CHANNEL_ID = "call_recording_channel";
    private static final int NOTIFICATION_ID = 1001;

    // Delay before scanning for recording (phone needs time to save the file)
    private static final long SCAN_DELAY_MS = 3000; // 3 seconds
    private static final int MAX_RETRIES = 5;
    private static final long RETRY_DELAY_MS = 2000; // 2 seconds between retries

    // Main thread handler — only for UI updates (notifications)
    private Handler mainHandler;

    // Background thread handler — for file scanning and uploading
    private HandlerThread backgroundThread;
    private Handler backgroundHandler;

    private RecordingUploader uploader;

    @Override
    public void onCreate() {
        super.onCreate();

        // Main thread handler for notification updates only
        mainHandler = new Handler(Looper.getMainLooper());

        // Dedicated background thread for file I/O (scanning + uploading)
        backgroundThread = new HandlerThread("RecordingFinderThread");
        backgroundThread.start();
        backgroundHandler = new Handler(backgroundThread.getLooper());

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

            Log.i(TAG, "Will scan for recording in " + SCAN_DELAY_MS + "ms (on background thread)...");

            // Delay on background thread — no main thread blocking at all
            backgroundHandler.postDelayed(() -> {
                findAndUploadWithRetry(callStartTime, phoneNumber, shipmentId,
                        logId, callDuration, 0);
            }, SCAN_DELAY_MS);
        } else {
            stopSelf();
        }

        return START_NOT_STICKY;
    }

    /**
     * Find recording and upload — runs entirely on background thread.
     * Retries up to MAX_RETRIES times with RETRY_DELAY_MS between attempts.
     */
    private void findAndUploadWithRetry(long callStartTime, String phoneNumber,
                                         String shipmentId, String logId,
                                         int callDuration, int attempt) {
        Log.i(TAG, "Scanning for recording (attempt " + (attempt + 1) + "/" + MAX_RETRIES + ")...");

        // Pass context so RecordingFinder can cache the successful directory
        File recording = RecordingFinder.findLatestRecording(callStartTime, phoneNumber, this);

        if (recording != null && recording.exists()) {
            Log.i(TAG, "Found recording: " + recording.getAbsolutePath());

            // Update notification on main thread
            mainHandler.post(() -> updateNotification("Uploading recording..."));

            // Upload (already on background thread, no need for new Thread)
            SharedPreferences prefs = getSharedPreferences("telesms_call", MODE_PRIVATE);
            String authCookie = prefs.getString("auth_cookie", "");

            boolean success = uploader.upload(recording, shipmentId, logId,
                    phoneNumber, callDuration, authCookie);

            if (success) {
                Log.i(TAG, "Recording uploaded successfully!");
                mainHandler.post(() -> updateNotification("Recording uploaded ✓"));

                // Store result for the WebView to pick up
                prefs.edit()
                        .putBoolean("pending_recording_upload", false)
                        .putString("last_recording_path", recording.getAbsolutePath())
                        .putBoolean("last_upload_success", true)
                        .apply();
            } else {
                Log.e(TAG, "Recording upload failed");
                mainHandler.post(() -> updateNotification("Upload failed — will retry later"));

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
            mainHandler.postDelayed(this::stopSelf, 2000);

        } else if (attempt < MAX_RETRIES - 1) {
            // Retry on background thread — recording might not be saved yet
            Log.d(TAG, "No recording found yet, retrying in " + RETRY_DELAY_MS + "ms...");
            backgroundHandler.postDelayed(() -> {
                findAndUploadWithRetry(callStartTime, phoneNumber, shipmentId,
                        logId, callDuration, attempt + 1);
            }, RETRY_DELAY_MS);

        } else {
            Log.w(TAG, "No recording found after " + MAX_RETRIES + " attempts");
            mainHandler.post(() -> updateNotification("No recording found"));

            SharedPreferences prefs = getSharedPreferences("telesms_call", MODE_PRIVATE);
            prefs.edit()
                    .putBoolean("pending_recording_upload", false)
                    .putBoolean("last_upload_success", false)
                    .putString("last_recording_path", "")
                    .apply();

            mainHandler.postDelayed(this::stopSelf, 2000);
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

    @Override
    public void onDestroy() {
        super.onDestroy();
        // Clean up the background thread
        if (backgroundThread != null) {
            backgroundThread.quitSafely();
        }
    }

    @Nullable
    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }
}
