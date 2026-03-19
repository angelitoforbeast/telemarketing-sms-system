package com.telesms.app;

import android.content.Context;
import android.content.SharedPreferences;
import android.media.AudioFormat;
import android.media.AudioRecord;
import android.media.MediaRecorder;
import android.os.Build;
import android.os.Environment;
import android.telecom.Call;
import android.telecom.VideoProfile;
import android.util.Log;

import java.io.File;
import java.io.FileOutputStream;
import java.io.IOException;
import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Date;
import java.util.List;
import java.util.Locale;

/**
 * Singleton that manages the current call state and call recording.
 * Uses MediaRecorder (primary) for better device compatibility,
 * with AudioRecord as fallback.
 */
public class CallManager {

    private static final String TAG = "CallManager";
    private static CallManager instance;

    // Current call
    private Call currentCall;
    private long callStartTime;
    private String currentPhoneNumber;
    private String currentShipmentId;
    private String currentLogId;

    // Recording - MediaRecorder approach (primary)
    private MediaRecorder mediaRecorder;
    // Recording - AudioRecord approach (fallback)
    private AudioRecord audioRecord;
    private Thread recordingThread;

    private boolean isRecording = false;
    private File recordingFile;
    private int recordingAudioSource = -1;
    private String recordingMethod = "none"; // "mediarecorder" or "audiorecord"

    // Listeners
    private final List<CallStateListener> listeners = new ArrayList<>();

    public interface CallStateListener {
        void onCallStateChanged(int state, String phoneNumber);
        void onRecordingStateChanged(boolean recording);
    }

    private CallManager() {}

    public static synchronized CallManager getInstance() {
        if (instance == null) {
            instance = new CallManager();
        }
        return instance;
    }

    // ==================== Call Management ====================

    public void setCall(Call call) {
        this.currentCall = call;
        if (call != null) {
            call.registerCallback(callCallback);
        }
    }

    public Call getCall() {
        return currentCall;
    }

    public void answerCall() {
        if (currentCall != null) {
            currentCall.answer(VideoProfile.STATE_AUDIO_ONLY);
        }
    }

    public void rejectCall() {
        if (currentCall != null) {
            currentCall.reject(false, null);
        }
    }

    public void hangupCall() {
        if (currentCall != null) {
            currentCall.disconnect();
        }
    }

    public void holdCall() {
        if (currentCall != null) {
            currentCall.hold();
        }
    }

    public void unholdCall() {
        if (currentCall != null) {
            currentCall.unhold();
        }
    }

    public void setMute(boolean mute) {
        // Mute is handled via InCallService.setMuted()
    }

    public boolean isCallActive() {
        return currentCall != null && currentCall.getState() == Call.STATE_ACTIVE;
    }

    public int getCallState() {
        return currentCall != null ? currentCall.getState() : Call.STATE_DISCONNECTED;
    }

    public long getCallStartTime() {
        return callStartTime;
    }

    public String getCurrentPhoneNumber() {
        return currentPhoneNumber;
    }

    // ==================== Call Context (from WebView) ====================

    public void setCallContext(String phoneNumber, String shipmentId, String logId) {
        this.currentPhoneNumber = phoneNumber;
        this.currentShipmentId = shipmentId;
        this.currentLogId = logId;
    }

    public void loadCallContext(Context context) {
        SharedPreferences prefs = context.getSharedPreferences("telesms_call", Context.MODE_PRIVATE);
        this.currentPhoneNumber = prefs.getString("current_phone_number", "");
        this.currentShipmentId = prefs.getString("current_shipment_id", "");
        this.currentLogId = prefs.getString("current_log_id", "");
    }

    public String getShipmentId() { return currentShipmentId; }
    public String getLogId() { return currentLogId; }

    // Recording fallback
    private boolean useBuiltInRecorderFallback = false;
    private long recordingStartTimeMs = 0;

    // ==================== Call Recording ====================

    /**
     * Start recording the call audio.
     * Strategy:
     * 1. If Accessibility Service is active, prioritize VOICE_RECOGNITION (proven to work)
     * 2. Try MediaRecorder with various audio sources
     * 3. Fallback to AudioRecord with silence detection
     * 4. If all fail, mark for built-in recorder file search after call ends
     */
    public void startRecording(Context context) {
        if (isRecording) {
            Log.w(TAG, "Already recording");
            return;
        }

        recordingStartTimeMs = System.currentTimeMillis();
        useBuiltInRecorderFallback = false;

        boolean accessibilityActive = TeleSMSAccessibilityService.isEnabled(context);
        Log.i(TAG, "Accessibility Service active: " + accessibilityActive);

        // Choose audio source order based on accessibility service status
        int[] sources;
        if (accessibilityActive) {
            // When Accessibility Service is active, VOICE_RECOGNITION works best
            sources = new int[] {
                MediaRecorder.AudioSource.VOICE_RECOGNITION,
                MediaRecorder.AudioSource.VOICE_COMMUNICATION,
                MediaRecorder.AudioSource.VOICE_CALL,
                MediaRecorder.AudioSource.MIC
            };
            Log.i(TAG, "Using accessibility-optimized source order (VOICE_RECOGNITION first)");
        } else {
            sources = new int[] {
                MediaRecorder.AudioSource.VOICE_CALL,
                MediaRecorder.AudioSource.VOICE_COMMUNICATION,
                MediaRecorder.AudioSource.VOICE_RECOGNITION,
                MediaRecorder.AudioSource.MIC
            };
            Log.i(TAG, "Using standard source order (VOICE_CALL first)");
        }

        // Try MediaRecorder first (better compatibility, outputs .m4a)
        for (int source : sources) {
            File file = createRecordingFile(context, ".m4a");
            if (file == null) continue;

            if (tryStartMediaRecorder(source, file)) {
                recordingFile = file;
                recordingAudioSource = source;
                recordingMethod = "mediarecorder";
                isRecording = true;
                Log.i(TAG, "Recording started with MediaRecorder + " + getAudioSourceName(source));
                notifyRecordingState(true);
                return;
            } else {
                file.delete();
            }
        }

        // Fallback: AudioRecord with silence detection
        Log.w(TAG, "MediaRecorder failed for all sources, trying AudioRecord fallback");
        for (int source : sources) {
            File file = createRecordingFile(context, ".wav");
            if (file == null) continue;

            if (tryStartAudioRecord(source, file)) {
                recordingFile = file;
                recordingAudioSource = source;
                recordingMethod = "audiorecord";
                isRecording = true;
                Log.i(TAG, "Recording started with AudioRecord + " + getAudioSourceName(source));
                notifyRecordingState(true);
                return;
            } else {
                file.delete();
            }
        }

        // All methods failed - mark for built-in recorder fallback
        Log.w(TAG, "All recording methods failed. Will search for built-in recorder file after call ends.");
        useBuiltInRecorderFallback = true;
        isRecording = true; // Mark as "recording" so the UI shows REC indicator
        notifyRecordingState(true);
    }

    /**
     * Try to start recording using MediaRecorder.
     * MediaRecorder is more compatible with most Android phones for call recording.
     */
    private boolean tryStartMediaRecorder(int audioSource, File outputFile) {
        try {
            mediaRecorder = new MediaRecorder();
            mediaRecorder.setAudioSource(audioSource);
            mediaRecorder.setOutputFormat(MediaRecorder.OutputFormat.MPEG_4);
            mediaRecorder.setAudioEncoder(MediaRecorder.AudioEncoder.AAC);
            mediaRecorder.setAudioSamplingRate(44100);
            mediaRecorder.setAudioEncodingBitRate(128000);
            mediaRecorder.setAudioChannels(1);
            mediaRecorder.setOutputFile(outputFile.getAbsolutePath());

            mediaRecorder.prepare();
            mediaRecorder.start();

            Log.i(TAG, "MediaRecorder started successfully with source " + getAudioSourceName(audioSource));
            return true;

        } catch (IllegalStateException e) {
            Log.w(TAG, "MediaRecorder IllegalStateException for source " + getAudioSourceName(audioSource) + ": " + e.getMessage());
            releaseMediaRecorder();
            return false;
        } catch (IOException e) {
            Log.w(TAG, "MediaRecorder IOException for source " + getAudioSourceName(audioSource) + ": " + e.getMessage());
            releaseMediaRecorder();
            return false;
        } catch (RuntimeException e) {
            Log.w(TAG, "MediaRecorder RuntimeException for source " + getAudioSourceName(audioSource) + ": " + e.getMessage());
            releaseMediaRecorder();
            return false;
        } catch (Exception e) {
            Log.w(TAG, "MediaRecorder Exception for source " + getAudioSourceName(audioSource) + ": " + e.getMessage());
            releaseMediaRecorder();
            return false;
        }
    }

    private void releaseMediaRecorder() {
        if (mediaRecorder != null) {
            try {
                mediaRecorder.stop();
            } catch (Exception ignored) {}
            try {
                mediaRecorder.reset();
                mediaRecorder.release();
            } catch (Exception ignored) {}
            mediaRecorder = null;
        }
    }

    /**
     * Try to start recording using AudioRecord (fallback).
     * Includes silence detection - if first 2 seconds are all zeros, reject this source.
     */
    private boolean tryStartAudioRecord(int audioSource, File outputFile) {
        int sampleRate = 16000;
        int channelConfig = AudioFormat.CHANNEL_IN_MONO;
        int audioFormat = AudioFormat.ENCODING_PCM_16BIT;
        int bufferSize = AudioRecord.getMinBufferSize(sampleRate, channelConfig, audioFormat);

        if (bufferSize == AudioRecord.ERROR_BAD_VALUE || bufferSize == AudioRecord.ERROR) {
            Log.w(TAG, "Invalid buffer size for source " + getAudioSourceName(audioSource));
            return false;
        }

        bufferSize = Math.max(bufferSize * 2, 8192);

        try {
            audioRecord = new AudioRecord(audioSource, sampleRate, channelConfig, audioFormat, bufferSize);

            if (audioRecord.getState() != AudioRecord.STATE_INITIALIZED) {
                Log.w(TAG, "AudioRecord not initialized for source " + getAudioSourceName(audioSource));
                audioRecord.release();
                audioRecord = null;
                return false;
            }

            audioRecord.startRecording();

            // Read test data and check for silence
            short[] testBuffer = new short[sampleRate]; // 1 second of audio
            int totalRead = 0;
            int maxAmplitude = 0;
            int attempts = 0;

            while (totalRead < testBuffer.length && attempts < 10) {
                int read = audioRecord.read(testBuffer, totalRead, Math.min(1600, testBuffer.length - totalRead));
                if (read > 0) {
                    for (int i = totalRead; i < totalRead + read; i++) {
                        int abs = Math.abs(testBuffer[i]);
                        if (abs > maxAmplitude) maxAmplitude = abs;
                    }
                    totalRead += read;
                } else {
                    break;
                }
                attempts++;
            }

            Log.i(TAG, "AudioRecord test for " + getAudioSourceName(audioSource) 
                    + ": read=" + totalRead + " samples, maxAmplitude=" + maxAmplitude);

            // If max amplitude is 0 after reading 1 second, this source produces silence
            if (maxAmplitude == 0 && totalRead > 0) {
                Log.w(TAG, "AudioRecord produces SILENCE for source " + getAudioSourceName(audioSource) + " - skipping");
                audioRecord.stop();
                audioRecord.release();
                audioRecord = null;
                return false;
            }

            // Source produces actual audio, start the recording thread
            recordingFile = outputFile;
            final int finalBufferSize = bufferSize;
            final short[] initialData = testBuffer;
            final int initialDataLength = totalRead;

            recordingThread = new Thread(() -> {
                writeAudioData(finalBufferSize, sampleRate, initialData, initialDataLength);
            }, "CallRecordingThread");
            recordingThread.setPriority(Thread.MAX_PRIORITY);
            recordingThread.start();

            return true;

        } catch (SecurityException e) {
            Log.w(TAG, "SecurityException for source " + getAudioSourceName(audioSource) + ": " + e.getMessage());
            if (audioRecord != null) {
                try { audioRecord.release(); } catch (Exception ignored) {}
                audioRecord = null;
            }
            return false;
        } catch (Exception e) {
            Log.w(TAG, "Exception for source " + getAudioSourceName(audioSource) + ": " + e.getMessage());
            if (audioRecord != null) {
                try { audioRecord.release(); } catch (Exception ignored) {}
                audioRecord = null;
            }
            return false;
        }
    }

    /**
     * Write audio data from AudioRecord to a WAV file.
     * Includes the initial test data that was already read.
     */
    private void writeAudioData(int bufferSize, int sampleRate, short[] initialData, int initialDataLength) {
        short[] buffer = new short[bufferSize / 2];
        File tempFile = new File(recordingFile.getAbsolutePath() + ".pcm");

        try (FileOutputStream fos = new FileOutputStream(tempFile)) {
            // Write initial test data first
            if (initialData != null && initialDataLength > 0) {
                byte[] byteBuffer = new byte[initialDataLength * 2];
                for (int i = 0; i < initialDataLength; i++) {
                    byteBuffer[i * 2] = (byte) (initialData[i] & 0xFF);
                    byteBuffer[i * 2 + 1] = (byte) ((initialData[i] >> 8) & 0xFF);
                }
                fos.write(byteBuffer);
            }

            // Continue recording
            while (isRecording && audioRecord != null) {
                int read = audioRecord.read(buffer, 0, buffer.length);
                if (read > 0) {
                    byte[] byteBuffer = new byte[read * 2];
                    for (int i = 0; i < read; i++) {
                        byteBuffer[i * 2] = (byte) (buffer[i] & 0xFF);
                        byteBuffer[i * 2 + 1] = (byte) ((buffer[i] >> 8) & 0xFF);
                    }
                    fos.write(byteBuffer);
                }
            }
        } catch (IOException e) {
            Log.e(TAG, "Error writing audio data: " + e.getMessage());
        }

        // Convert PCM to WAV
        try {
            pcmToWav(tempFile, recordingFile, sampleRate, 1, 16);
            tempFile.delete();
            Log.i(TAG, "Recording saved: " + recordingFile.getAbsolutePath()
                    + " (" + recordingFile.length() / 1024 + " KB)");
        } catch (IOException e) {
            Log.e(TAG, "Error converting PCM to WAV: " + e.getMessage());
        }
    }

    /**
     * Stop recording and finalize the file.
     */
    public void stopRecording() {
        if (!isRecording) return;

        isRecording = false;

        if ("mediarecorder".equals(recordingMethod)) {
            // Stop MediaRecorder
            if (mediaRecorder != null) {
                try {
                    mediaRecorder.stop();
                    mediaRecorder.reset();
                    mediaRecorder.release();
                    Log.i(TAG, "MediaRecorder stopped. File: " + 
                        (recordingFile != null ? recordingFile.length() / 1024 + " KB" : "null"));
                } catch (RuntimeException e) {
                    Log.e(TAG, "Error stopping MediaRecorder: " + e.getMessage());
                    if (recordingFile != null && recordingFile.exists()) {
                        recordingFile.delete();
                        recordingFile = null;
                    }
                } catch (Exception e) {
                    Log.e(TAG, "Error releasing MediaRecorder: " + e.getMessage());
                }
                mediaRecorder = null;
            }
        } else if ("audiorecord".equals(recordingMethod)) {
            // Stop AudioRecord
            if (audioRecord != null) {
                try {
                    audioRecord.stop();
                    audioRecord.release();
                } catch (Exception e) {
                    Log.e(TAG, "Error stopping AudioRecord: " + e.getMessage());
                }
                audioRecord = null;
            }

            if (recordingThread != null) {
                try {
                    recordingThread.join(5000);
                } catch (InterruptedException e) {
                    Log.e(TAG, "Recording thread interrupted");
                }
                recordingThread = null;
            }
        }

        notifyRecordingState(false);
        Log.i(TAG, "Recording stopped. Method: " + recordingMethod 
                + ", Source: " + getAudioSourceName(recordingAudioSource));
    }

    /**
     * Search for a recording file from the phone's built-in call recorder.
     * Called after call ends if our own recording failed or produced silence.
     */
    public File findBuiltInRecording(Context context) {
        long endTime = System.currentTimeMillis();
        long startTime = recordingStartTimeMs > 0 ? recordingStartTimeMs : (endTime - 300000); // 5 min window
        
        Log.i(TAG, "Searching for built-in recorder file...");
        File found = CallRecordingFinder.findRecentRecording(context, startTime, endTime);
        
        if (found != null) {
            Log.i(TAG, "Found built-in recording: " + found.getAbsolutePath() 
                    + " (" + found.length() / 1024 + " KB)");
            recordingFile = found;
            recordingMethod = "builtin_recorder";
            return found;
        } else {
            Log.w(TAG, "No built-in recording found");
            return null;
        }
    }

    /**
     * Check if we should search for built-in recorder files.
     */
    public boolean shouldUseBuiltInRecorderFallback() {
        return useBuiltInRecorderFallback;
    }

    /**
     * Check if the current recording file has actual audio content (not silence).
     * Only works for WAV files.
     */
    public boolean hasAudioContent() {
        if (recordingFile == null || !recordingFile.exists()) return false;
        
        // For MediaRecorder files (.m4a), if size > 10KB, assume it has content
        if (recordingFile.getName().endsWith(".m4a")) {
            return recordingFile.length() > 10000;
        }
        
        // For WAV files, check if there's non-zero audio data
        if (recordingFile.getName().endsWith(".wav") && recordingFile.length() > 44) {
            try (java.io.FileInputStream fis = new java.io.FileInputStream(recordingFile)) {
                fis.skip(44); // Skip WAV header
                byte[] buf = new byte[8192];
                int read;
                while ((read = fis.read(buf)) > 0) {
                    for (int i = 0; i < read; i++) {
                        if (buf[i] != 0) return true; // Found non-zero data
                    }
                }
            } catch (Exception e) {
                Log.e(TAG, "Error checking audio content: " + e.getMessage());
            }
            return false; // All zeros = silence
        }
        
        return recordingFile.length() > 5000;
    }

    public boolean isRecording() {
        return isRecording;
    }

    public File getRecordingFile() {
        return recordingFile;
    }

    public String getAudioSourceName(int source) {
        switch (source) {
            case MediaRecorder.AudioSource.VOICE_CALL: return "VOICE_CALL";
            case MediaRecorder.AudioSource.VOICE_COMMUNICATION: return "VOICE_COMMUNICATION";
            case MediaRecorder.AudioSource.VOICE_RECOGNITION: return "VOICE_RECOGNITION";
            case MediaRecorder.AudioSource.MIC: return "MIC";
            default: return "UNKNOWN(" + source + ")";
        }
    }

    public String getRecordingMethod() {
        return recordingMethod;
    }

    /**
     * Create a recording file in the app's recording directory.
     */
    private File createRecordingFile(Context context, String extension) {
        try {
            File recordingDir = new File(context.getExternalFilesDir(null), "recordings");
            if (!recordingDir.exists() && !recordingDir.mkdirs()) {
                Log.e(TAG, "Failed to create recording directory");
                return null;
            }

            String timestamp = new SimpleDateFormat("yyyyMMdd_HHmmss", Locale.US).format(new Date());
            String cleanNumber = currentPhoneNumber != null ?
                    currentPhoneNumber.replaceAll("[^0-9]", "") : "unknown";
            String filename = "call_" + timestamp + "_" + cleanNumber + extension;

            return new File(recordingDir, filename);
        } catch (Exception e) {
            Log.e(TAG, "Error creating recording file: " + e.getMessage());
            return null;
        }
    }

    /**
     * Convert raw PCM file to WAV format with proper headers.
     */
    private void pcmToWav(File pcmFile, File wavFile, int sampleRate, int channels, int bitsPerSample)
            throws IOException {
        long pcmSize = pcmFile.length();
        long dataSize = pcmSize;
        long fileSize = 36 + dataSize;

        byte[] header = new byte[44];
        header[0] = 'R'; header[1] = 'I'; header[2] = 'F'; header[3] = 'F';
        header[4] = (byte)(fileSize & 0xff);
        header[5] = (byte)((fileSize >> 8) & 0xff);
        header[6] = (byte)((fileSize >> 16) & 0xff);
        header[7] = (byte)((fileSize >> 24) & 0xff);
        header[8] = 'W'; header[9] = 'A'; header[10] = 'V'; header[11] = 'E';
        header[12] = 'f'; header[13] = 'm'; header[14] = 't'; header[15] = ' ';
        header[16] = 16; header[17] = 0; header[18] = 0; header[19] = 0;
        header[20] = 1; header[21] = 0;
        header[22] = (byte)(channels & 0xff); header[23] = 0;
        header[24] = (byte)(sampleRate & 0xff);
        header[25] = (byte)((sampleRate >> 8) & 0xff);
        header[26] = (byte)((sampleRate >> 16) & 0xff);
        header[27] = (byte)((sampleRate >> 24) & 0xff);
        int byteRate = sampleRate * channels * bitsPerSample / 8;
        header[28] = (byte)(byteRate & 0xff);
        header[29] = (byte)((byteRate >> 8) & 0xff);
        header[30] = (byte)((byteRate >> 16) & 0xff);
        header[31] = (byte)((byteRate >> 24) & 0xff);
        int blockAlign = channels * bitsPerSample / 8;
        header[32] = (byte)(blockAlign & 0xff); header[33] = 0;
        header[34] = (byte)(bitsPerSample & 0xff); header[35] = 0;
        header[36] = 'd'; header[37] = 'a'; header[38] = 't'; header[39] = 'a';
        header[40] = (byte)(dataSize & 0xff);
        header[41] = (byte)((dataSize >> 8) & 0xff);
        header[42] = (byte)((dataSize >> 16) & 0xff);
        header[43] = (byte)((dataSize >> 24) & 0xff);

        try (FileOutputStream wavOut = new FileOutputStream(wavFile);
             java.io.FileInputStream pcmIn = new java.io.FileInputStream(pcmFile)) {
            wavOut.write(header);
            byte[] buf = new byte[8192];
            int read;
            while ((read = pcmIn.read(buf)) != -1) {
                wavOut.write(buf, 0, read);
            }
        }
    }

    // ==================== Call Callback ====================

    private final Call.Callback callCallback = new Call.Callback() {
        @Override
        public void onStateChanged(Call call, int state) {
            Log.i(TAG, "Call state changed: " + stateToString(state));

            switch (state) {
                case Call.STATE_ACTIVE:
                    callStartTime = System.currentTimeMillis();
                    break;

                case Call.STATE_DISCONNECTED:
                    stopRecording();
                    currentCall = null;
                    break;
            }

            notifyCallState(state, currentPhoneNumber);
        }
    };

    // ==================== Listeners ====================

    public void addListener(CallStateListener listener) {
        if (!listeners.contains(listener)) {
            listeners.add(listener);
        }
    }

    public void removeListener(CallStateListener listener) {
        listeners.remove(listener);
    }

    private void notifyCallState(int state, String phoneNumber) {
        for (CallStateListener listener : new ArrayList<>(listeners)) {
            listener.onCallStateChanged(state, phoneNumber);
        }
    }

    private void notifyRecordingState(boolean recording) {
        for (CallStateListener listener : new ArrayList<>(listeners)) {
            listener.onRecordingStateChanged(recording);
        }
    }

    // ==================== Utilities ====================

    public static String stateToString(int state) {
        switch (state) {
            case Call.STATE_NEW: return "NEW";
            case Call.STATE_DIALING: return "DIALING";
            case Call.STATE_RINGING: return "RINGING";
            case Call.STATE_HOLDING: return "HOLDING";
            case Call.STATE_ACTIVE: return "ACTIVE";
            case Call.STATE_DISCONNECTED: return "DISCONNECTED";
            case Call.STATE_CONNECTING: return "CONNECTING";
            case Call.STATE_DISCONNECTING: return "DISCONNECTING";
            case Call.STATE_SELECT_PHONE_ACCOUNT: return "SELECT_PHONE_ACCOUNT";
            case Call.STATE_PULLING_CALL: return "PULLING_CALL";
            default: return "UNKNOWN(" + state + ")";
        }
    }

    /**
     * Save call result to SharedPreferences for the WebView to pick up.
     */
    public void saveCallResult(Context context) {
        SharedPreferences prefs = context.getSharedPreferences("telesms_call", Context.MODE_PRIVATE);
        int duration = 0;
        if (callStartTime > 0) {
            duration = (int) ((System.currentTimeMillis() - callStartTime) / 1000);
        }

        SharedPreferences.Editor editor = prefs.edit()
                .putBoolean("call_active", false)
                .putInt("last_call_duration", duration)
                .putLong("call_end_time", System.currentTimeMillis());

        if (recordingFile != null && recordingFile.exists() && recordingFile.length() > 1000) {
            editor.putString("last_recording_path", recordingFile.getAbsolutePath());
            editor.putBoolean("pending_recording_upload", true);
            editor.putString("recording_audio_source", getAudioSourceName(recordingAudioSource));
            editor.putString("recording_method", recordingMethod);
        } else {
            editor.putString("last_recording_path", "");
            editor.putBoolean("pending_recording_upload", false);
        }

        editor.apply();
        Log.i(TAG, "Call result saved: duration=" + duration + "s, method=" + recordingMethod
                + ", recording=" + (recordingFile != null ? recordingFile.getAbsolutePath() : "none"));
    }
}
