package com.telesms.app;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.telephony.TelephonyManager;
import android.util.Log;

/**
 * Receives phone state changes (RINGING, OFFHOOK, IDLE)
 * to detect when calls start and end.
 */
public class PhoneCallReceiver extends BroadcastReceiver {

    private static final String TAG = "PhoneCallReceiver";

    @Override
    public void onReceive(Context context, Intent intent) {
        if (intent == null || intent.getAction() == null) return;

        if (!TelephonyManager.ACTION_PHONE_STATE_CHANGED.equals(intent.getAction())) return;

        String state = intent.getStringExtra(TelephonyManager.EXTRA_STATE);
        SharedPreferences prefs = context.getSharedPreferences("telesms_call", Context.MODE_PRIVATE);

        if (TelephonyManager.EXTRA_STATE_OFFHOOK.equals(state)) {
            // Call connected (outgoing or answered)
            long now = System.currentTimeMillis();
            prefs.edit()
                    .putLong("call_start_time", now)
                    .putBoolean("call_active", true)
                    .apply();
            Log.i(TAG, "Call started at: " + now);

        } else if (TelephonyManager.EXTRA_STATE_IDLE.equals(state)) {
            // Call ended
            boolean wasActive = prefs.getBoolean("call_active", false);

            if (wasActive) {
                long callStartTime = prefs.getLong("call_start_time", 0);
                long callEndTime = System.currentTimeMillis();
                int callDuration = (int) ((callEndTime - callStartTime) / 1000);

                String phoneNumber = prefs.getString("current_phone_number", "");
                String shipmentId = prefs.getString("current_shipment_id", "");
                String logId = prefs.getString("current_log_id", "");

                prefs.edit()
                        .putBoolean("call_active", false)
                        .putLong("call_end_time", callEndTime)
                        .putInt("last_call_duration", callDuration)
                        .putBoolean("pending_recording_upload", true)
                        .apply();

                Log.i(TAG, "Call ended. Duration: " + callDuration + "s, Phone: " + phoneNumber);

                // Start the recording service to find and upload the recording
                Intent serviceIntent = new Intent(context, CallRecordingService.class);
                serviceIntent.setAction("FIND_AND_UPLOAD");
                serviceIntent.putExtra("call_start_time", callStartTime);
                serviceIntent.putExtra("call_end_time", callEndTime);
                serviceIntent.putExtra("call_duration", callDuration);
                serviceIntent.putExtra("phone_number", phoneNumber);
                serviceIntent.putExtra("shipment_id", shipmentId);
                serviceIntent.putExtra("log_id", logId);

                try {
                    context.startForegroundService(serviceIntent);
                } catch (Exception e) {
                    Log.e(TAG, "Failed to start recording service: " + e.getMessage());
                    // Try as regular service for older Android
                    try {
                        context.startService(serviceIntent);
                    } catch (Exception e2) {
                        Log.e(TAG, "Failed to start service at all: " + e2.getMessage());
                    }
                }
            }

        } else if (TelephonyManager.EXTRA_STATE_RINGING.equals(state)) {
            // Incoming call ringing — not relevant for outgoing telemarketing calls
            Log.d(TAG, "Phone ringing (incoming call)");
        }
    }
}
