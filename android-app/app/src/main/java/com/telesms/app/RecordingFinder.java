package com.telesms.app;

import android.os.Environment;
import android.util.Log;

import java.io.File;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.Comparator;
import java.util.List;

/**
 * Scans known call recording directories across different phone brands
 * to find the most recent recording after a call ends.
 */
public class RecordingFinder {

    private static final String TAG = "RecordingFinder";

    // Known recording paths for various phone brands
    private static final String[] RECORDING_PATHS = {
        // Xiaomi / MIUI
        "MIUI/sound_recorder/call_rec",
        "MIUI/sound_recorder",
        // Samsung
        "Recordings/Call",
        "Call",
        // Realme / OPPO / ColorOS
        "Record/Call",
        "Recordings",
        "Music/Recordings/Call Recordings",
        // Vivo
        "Record/Call",
        "Recordings/Record/Call",
        // Huawei
        "Sounds/CallRecord",
        "record",
        // OnePlus
        "Recordings",
        "Record/PhoneRecord",
        // General / Stock Android
        "Recordings/Call",
        "AudioRecorder",
        "Call Recordings",
        "CallRecordings",
        "PhoneCallRecords",
        // Google Pixel (if available)
        "com.google.android.apps.recorder/files",
    };

    // Supported audio file extensions
    private static final String[] AUDIO_EXTENSIONS = {
        ".mp3", ".m4a", ".amr", ".wav", ".ogg", ".3gp", ".aac", ".opus"
    };

    /**
     * Find the most recent recording file created after the given timestamp.
     *
     * @param callStartTime  Timestamp when the call started (millis)
     * @param phoneNumber    The phone number that was called (for matching)
     * @return The most recent recording file, or null if not found
     */
    public static File findLatestRecording(long callStartTime, String phoneNumber) {
        File storageRoot = Environment.getExternalStorageDirectory();
        List<File> candidates = new ArrayList<>();

        for (String path : RECORDING_PATHS) {
            File dir = new File(storageRoot, path);
            if (dir.exists() && dir.isDirectory()) {
                Log.d(TAG, "Scanning directory: " + dir.getAbsolutePath());
                scanDirectory(dir, callStartTime, phoneNumber, candidates);
            }
        }

        if (candidates.isEmpty()) {
            Log.d(TAG, "No recording candidates found. Doing deep scan...");
            // Deep scan: check all directories in storage root
            deepScan(storageRoot, callStartTime, phoneNumber, candidates, 0);
        }

        if (candidates.isEmpty()) {
            Log.w(TAG, "No recording found after call at " + callStartTime);
            return null;
        }

        // Sort by last modified time (newest first)
        candidates.sort((a, b) -> Long.compare(b.lastModified(), a.lastModified()));

        File best = candidates.get(0);
        Log.i(TAG, "Found recording: " + best.getAbsolutePath() +
                " (modified: " + best.lastModified() + ", size: " + best.length() + " bytes)");
        return best;
    }

    private static void scanDirectory(File dir, long callStartTime, String phoneNumber, List<File> candidates) {
        File[] files = dir.listFiles();
        if (files == null) return;

        for (File file : files) {
            if (file.isFile() && isAudioFile(file) && file.lastModified() >= callStartTime - 5000) {
                // File was created/modified around or after the call started
                // Give 5 second buffer before call start
                if (file.length() > 1000) { // At least 1KB (not empty)
                    Log.d(TAG, "Candidate: " + file.getName() +
                            " (modified: " + file.lastModified() + ", size: " + file.length() + ")");

                    // Bonus: check if filename contains the phone number
                    if (phoneNumber != null && !phoneNumber.isEmpty()) {
                        String cleanNumber = phoneNumber.replaceAll("[^0-9]", "");
                        if (file.getName().contains(cleanNumber) ||
                            file.getName().contains(cleanNumber.substring(Math.max(0, cleanNumber.length() - 10)))) {
                            // Phone number match — high confidence
                            Log.d(TAG, "Phone number match in filename!");
                            candidates.add(0, file); // Add to front (highest priority)
                            continue;
                        }
                    }
                    candidates.add(file);
                }
            }
        }
    }

    private static void deepScan(File dir, long callStartTime, String phoneNumber,
                                  List<File> candidates, int depth) {
        if (depth > 3) return; // Don't go too deep
        if (dir == null || !dir.exists()) return;

        File[] files = dir.listFiles();
        if (files == null) return;

        for (File file : files) {
            if (file.isDirectory()) {
                String name = file.getName().toLowerCase();
                // Only scan directories that might contain recordings
                if (name.contains("record") || name.contains("call") ||
                    name.contains("sound") || name.contains("audio") ||
                    name.contains("voice") || name.contains("miui")) {
                    deepScan(file, callStartTime, phoneNumber, candidates, depth + 1);
                }
            } else if (file.isFile() && isAudioFile(file) &&
                       file.lastModified() >= callStartTime - 5000 &&
                       file.length() > 1000) {
                candidates.add(file);
            }
        }
    }

    private static boolean isAudioFile(File file) {
        String name = file.getName().toLowerCase();
        for (String ext : AUDIO_EXTENSIONS) {
            if (name.endsWith(ext)) return true;
        }
        return false;
    }

    /**
     * Get all known recording directories that exist on this device.
     * Useful for debugging / showing the user which paths are being monitored.
     */
    public static List<String> getActiveRecordingPaths() {
        File storageRoot = Environment.getExternalStorageDirectory();
        List<String> activePaths = new ArrayList<>();

        for (String path : RECORDING_PATHS) {
            File dir = new File(storageRoot, path);
            if (dir.exists() && dir.isDirectory()) {
                activePaths.add(dir.getAbsolutePath());
            }
        }
        return activePaths;
    }
}
