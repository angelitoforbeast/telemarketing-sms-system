package com.telesms.app;

import android.accessibilityservice.AccessibilityService;
import android.accessibilityservice.AccessibilityServiceInfo;
import android.content.Context;
import android.content.SharedPreferences;
import android.util.Log;
import android.view.accessibility.AccessibilityEvent;

/**
 * TeleSMS Accessibility Service.
 * 
 * This service enables reliable call recording by being an active Accessibility Service.
 * When enabled, Android grants elevated audio access which allows the app to
 * capture call audio using VOICE_RECOGNITION or VOICE_COMMUNICATION audio sources.
 * 
 * The service itself is minimal - it doesn't process any accessibility events.
 * Its purpose is solely to be "active" so the audio recording system can work properly.
 */
public class TeleSMSAccessibilityService extends AccessibilityService {

    private static final String TAG = "TeleSMSAccessibility";
    private static boolean isServiceActive = false;

    @Override
    public void onServiceConnected() {
        super.onServiceConnected();
        isServiceActive = true;

        // Configure minimal accessibility service
        AccessibilityServiceInfo info = new AccessibilityServiceInfo();
        info.eventTypes = AccessibilityEvent.TYPE_NOTIFICATION_STATE_CHANGED;
        info.feedbackType = AccessibilityServiceInfo.FEEDBACK_GENERIC;
        info.notificationTimeout = 1000;
        info.flags = AccessibilityServiceInfo.FLAG_INCLUDE_NOT_IMPORTANT_VIEWS;
        setServiceInfo(info);

        // Save state to SharedPreferences so other components can check
        saveServiceState(true);

        Log.i(TAG, "TeleSMS Accessibility Service connected - call recording enabled");
    }

    @Override
    public void onAccessibilityEvent(AccessibilityEvent event) {
        // We don't need to process any events
        // The service just needs to be active for audio recording to work
    }

    @Override
    public void onInterrupt() {
        Log.w(TAG, "TeleSMS Accessibility Service interrupted");
    }

    @Override
    public void onDestroy() {
        isServiceActive = false;
        saveServiceState(false);
        Log.i(TAG, "TeleSMS Accessibility Service destroyed");
        super.onDestroy();
    }

    private void saveServiceState(boolean active) {
        try {
            SharedPreferences prefs = getSharedPreferences("telesms_prefs", Context.MODE_PRIVATE);
            prefs.edit().putBoolean("accessibility_service_active", active).apply();
        } catch (Exception e) {
            Log.e(TAG, "Error saving service state: " + e.getMessage());
        }
    }

    /**
     * Check if the Accessibility Service is currently active.
     */
    public static boolean isActive() {
        return isServiceActive;
    }

    /**
     * Check if the Accessibility Service is enabled (via SharedPreferences fallback).
     */
    public static boolean isEnabled(Context context) {
        if (isServiceActive) return true;
        try {
            SharedPreferences prefs = context.getSharedPreferences("telesms_prefs", Context.MODE_PRIVATE);
            return prefs.getBoolean("accessibility_service_active", false);
        } catch (Exception e) {
            return false;
        }
    }
}
