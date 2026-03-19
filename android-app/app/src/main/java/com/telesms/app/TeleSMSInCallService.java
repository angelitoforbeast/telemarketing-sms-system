package com.telesms.app;

import android.content.Intent;
import android.os.Build;
import android.telecom.Call;
import android.telecom.CallAudioState;
import android.telecom.InCallService;
import android.util.Log;

/**
 * InCallService implementation that makes TeleSMS the default dialer.
 * When TeleSMS is set as the default phone app, Android routes all
 * incoming and outgoing calls through this service.
 *
 * This enables:
 * 1. Custom in-call UI (InCallActivity)
 * 2. Direct call audio recording (via CallManager)
 * 3. Guaranteed call recording on all Android versions
 */
public class TeleSMSInCallService extends InCallService {

    private static final String TAG = "TeleSMSInCallService";
    private static TeleSMSInCallService instance;

    @Override
    public void onCreate() {
        super.onCreate();
        instance = this;
        Log.i(TAG, "InCallService created");
    }

    @Override
    public void onDestroy() {
        super.onDestroy();
        instance = null;
        Log.i(TAG, "InCallService destroyed");
    }

    /**
     * Called when a new call is added (incoming or outgoing).
     */
    @Override
    public void onCallAdded(Call call) {
        super.onCallAdded(call);
        Log.i(TAG, "Call added: " + (call.getDetails() != null && call.getDetails().getHandle() != null
                ? call.getDetails().getHandle() : "unknown"));

        // Set the call in CallManager
        CallManager callManager = CallManager.getInstance();
        callManager.setCall(call);

        // Load call context from SharedPreferences (set by TeleSMSJSBridge.makeCall)
        callManager.loadCallContext(this);

        // Extract phone number from call details
        if (call.getDetails() != null && call.getDetails().getHandle() != null) {
            String phoneNumber = call.getDetails().getHandle().getSchemeSpecificPart();
            if (phoneNumber != null && !phoneNumber.isEmpty()) {
                String shipmentId = callManager.getShipmentId();
                String logId = callManager.getLogId();
                callManager.setCallContext(phoneNumber, shipmentId, logId);
            }
        }

        // Launch the in-call activity
        Intent intent = new Intent(this, InCallActivity.class);
        intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_SINGLE_TOP);

        int callState = call.getState();
        if (callState == Call.STATE_RINGING) {
            intent.putExtra("call_type", "incoming");
        } else {
            intent.putExtra("call_type", "outgoing");
        }

        startActivity(intent);
    }

    /**
     * Called when a call is removed (ended).
     */
    @Override
    public void onCallRemoved(Call call) {
        super.onCallRemoved(call);
        Log.i(TAG, "Call removed");

        CallManager callManager = CallManager.getInstance();

        // Stop recording
        callManager.stopRecording();

        // Save call result for WebView
        callManager.saveCallResult(this);

        // Trigger recording upload
        triggerRecordingUpload(callManager);
    }

    /**
     * Set mute state for the current call.
     * Note: InCallService has its own setMuted() method - we call super.
     */
    public void toggleMute(boolean muted) {
        super.setMuted(muted);
        Log.d(TAG, "Mute set to: " + muted);
    }

    /**
     * Check if currently muted.
     */
    public boolean isMuted() {
        CallAudioState audioState = getCallAudioState();
        return audioState != null && audioState.isMuted();
    }

    /**
     * Toggle speaker mode.
     */
    public void setSpeaker(boolean speaker) {
        setAudioRoute(speaker ? CallAudioState.ROUTE_SPEAKER : CallAudioState.ROUTE_EARPIECE);
        Log.d(TAG, "Speaker set to: " + speaker);
    }

    /**
     * Check if speaker is on.
     */
    public boolean isSpeakerOn() {
        CallAudioState audioState = getCallAudioState();
        return audioState != null && (audioState.getRoute() & CallAudioState.ROUTE_SPEAKER) != 0;
    }

    /**
     * Get the current instance (for InCallActivity to access).
     */
    public static TeleSMSInCallService getInstance() {
        return instance;
    }

    /**
     * Trigger the upload of the recording after the call ends.
     * Checks for audio content and falls back to built-in recorder if needed.
     */
    private void triggerRecordingUpload(CallManager callManager) {
        new Thread(() -> {
            try {
                java.io.File recordingFile = callManager.getRecordingFile();
                String shipmentId = callManager.getShipmentId();
                String logId = callManager.getLogId();
                String phoneNumber = callManager.getCurrentPhoneNumber();

                // Check if our recording has actual audio content
                boolean hasAudio = callManager.hasAudioContent();
                Log.i(TAG, "Our recording has audio: " + hasAudio 
                        + " (file: " + (recordingFile != null ? recordingFile.length() / 1024 + "KB" : "null") + ")");

                // If our recording is silent or we used fallback mode, search for built-in recorder file
                if (!hasAudio || callManager.shouldUseBuiltInRecorderFallback()) {
                    Log.i(TAG, "Searching for built-in recorder file as fallback...");
                    
                    // Wait a moment for the built-in recorder to finish writing
                    try { Thread.sleep(2000); } catch (InterruptedException ignored) {}
                    
                    java.io.File builtInFile = callManager.findBuiltInRecording(TeleSMSInCallService.this);
                    if (builtInFile != null) {
                        Log.i(TAG, "Using built-in recorder file: " + builtInFile.getAbsolutePath());
                        recordingFile = builtInFile;
                        
                        // Delete our silent recording
                        java.io.File ourFile = callManager.getRecordingFile();
                        if (ourFile != null && ourFile != builtInFile && ourFile.exists()) {
                            ourFile.delete();
                        }
                    } else {
                        Log.w(TAG, "No built-in recorder file found either");
                    }
                }

                // Upload if we have a valid recording
                if (recordingFile != null && recordingFile.exists() && recordingFile.length() > 1000) {
                    Log.i(TAG, "Triggering recording upload: " + recordingFile.getAbsolutePath()
                            + " (" + recordingFile.length() / 1024 + " KB)");

                    RecordingUploader uploader = new RecordingUploader(TeleSMSInCallService.this);
                    boolean success = uploader.uploadWithRetry(
                            recordingFile, phoneNumber, shipmentId, logId, 3);

                    android.content.SharedPreferences prefs =
                            getSharedPreferences("telesms_call", MODE_PRIVATE);
                    prefs.edit()
                            .putBoolean("pending_recording_upload", false)
                            .putBoolean("last_upload_success", success)
                            .apply();

                    Log.i(TAG, "Recording upload " + (success ? "succeeded" : "failed"));
                } else {
                    Log.w(TAG, "No valid recording file to upload");
                }
            } catch (Exception e) {
                Log.e(TAG, "Error in recording upload process: " + e.getMessage());
            }
        }).start();
    }
}
