package com.telesms.app;

import android.content.Context;
import android.os.Environment;
import android.util.Log;

import java.io.File;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.Collections;
import java.util.Comparator;
import java.util.List;

/**
 * Finds call recording files created by the phone's built-in call recorder.
 * 
 * Different phone brands store recordings in different locations:
 * - Samsung: /storage/emulated/0/Call/ or /Recordings/Call/
 * - Xiaomi: /storage/emulated/0/MIUI/sound_recorder/call_rec/
 * - Realme/OPPO: /storage/emulated/0/Recordings/ or /Music/Recordings/
 * - Vivo: /storage/emulated/0/Record/Call/
 * - Huawei: /storage/emulated/0/Sounds/CallRecord/
 * - Stock Android: /storage/emulated/0/Recordings/
 */
public class CallRecordingFinder {

    private static final String TAG = "CallRecordingFinder";

    // Known recording directories for different phone brands
    private static final String[] RECORDING_DIRS = {
        // Samsung
        "Call",
        "Recordings/Call",
        "Record/Call",
        // Xiaomi / Redmi
        "MIUI/sound_recorder/call_rec",
        "MIUI/sound_recorder",
        // OPPO / Realme / OnePlus
        "Recordings",
        "Music/Recordings",
        "Record",
        // Vivo
        "Record/Call",
        "Recorder",
        // Huawei / Honor
        "Sounds/CallRecord",
        "Sounds",
        // General
        "PhoneRecord",
        "CallRecordings",
        "Call Recordings",
        "AudioRecorder",
        "VoiceRecorder",
        // Google Pixel
        "com.google.android.apps.tachyon/files"
    };

    // Audio file extensions to look for
    private static final String[] AUDIO_EXTENSIONS = {
        ".m4a", ".mp3", ".amr", ".3gp", ".wav", ".ogg", ".opus", ".aac", ".mp4"
    };

    /**
     * Find the most recent call recording file created within the given time window.
     * 
     * @param context Android context
     * @param callStartTimeMs When the call started (milliseconds since epoch)
     * @param callEndTimeMs When the call ended (milliseconds since epoch)
     * @return The recording file, or null if not found
     */
    public static File findRecentRecording(Context context, long callStartTimeMs, long callEndTimeMs) {
        // Add some buffer time (30 seconds before call start, 30 seconds after call end)
        long searchStart = callStartTimeMs - 30000;
        long searchEnd = callEndTimeMs + 30000;

        File sdcard = Environment.getExternalStorageDirectory();
        List<File> candidates = new ArrayList<>();

        Log.i(TAG, "Searching for recording files between " + searchStart + " and " + searchEnd);

        for (String dirPath : RECORDING_DIRS) {
            File dir = new File(sdcard, dirPath);
            if (dir.exists() && dir.isDirectory()) {
                Log.d(TAG, "Checking directory: " + dir.getAbsolutePath());
                findAudioFiles(dir, candidates, searchStart, searchEnd, 0);
            }
        }

        // Also check app-specific directories
        try {
            File appExtDir = context.getExternalFilesDir(null);
            if (appExtDir != null) {
                File parentDir = appExtDir.getParentFile();
                if (parentDir != null) {
                    // Check other app's recording directories
                    File[] appDirs = parentDir.listFiles();
                    if (appDirs != null) {
                        for (File appDir : appDirs) {
                            findAudioFiles(appDir, candidates, searchStart, searchEnd, 1);
                        }
                    }
                }
            }
        } catch (Exception e) {
            Log.w(TAG, "Error checking app directories: " + e.getMessage());
        }

        if (candidates.isEmpty()) {
            Log.w(TAG, "No recording files found in any known directory");
            return null;
        }

        // Sort by modification time (newest first) and return the most recent
        Collections.sort(candidates, (f1, f2) -> Long.compare(f2.lastModified(), f1.lastModified()));

        File best = candidates.get(0);
        Log.i(TAG, "Found recording: " + best.getAbsolutePath() 
                + " (" + best.length() / 1024 + " KB, modified: " + best.lastModified() + ")");

        return best;
    }

    /**
     * Recursively find audio files in a directory that were modified within the time window.
     */
    private static void findAudioFiles(File dir, List<File> results, long startTime, long endTime, int depth) {
        if (depth > 3 || dir == null || !dir.exists()) return; // Don't go too deep

        File[] files = dir.listFiles();
        if (files == null) return;

        for (File file : files) {
            if (file.isDirectory() && depth < 3) {
                findAudioFiles(file, results, startTime, endTime, depth + 1);
            } else if (file.isFile()) {
                String name = file.getName().toLowerCase();
                boolean isAudio = false;
                for (String ext : AUDIO_EXTENSIONS) {
                    if (name.endsWith(ext)) {
                        isAudio = true;
                        break;
                    }
                }

                if (isAudio && file.lastModified() >= startTime && file.lastModified() <= endTime
                        && file.length() > 5000) { // At least 5KB to be a real recording
                    Log.d(TAG, "Found candidate: " + file.getAbsolutePath() 
                            + " (" + file.length() / 1024 + " KB)");
                    results.add(file);
                }
            }
        }
    }

    /**
     * Get a list of all known recording directories that exist on this device.
     * Useful for debugging.
     */
    public static List<String> getExistingRecordingDirs() {
        List<String> existing = new ArrayList<>();
        File sdcard = Environment.getExternalStorageDirectory();

        for (String dirPath : RECORDING_DIRS) {
            File dir = new File(sdcard, dirPath);
            if (dir.exists() && dir.isDirectory()) {
                File[] files = dir.listFiles();
                int count = files != null ? files.length : 0;
                existing.add(dir.getAbsolutePath() + " (" + count + " files)");
            }
        }

        return existing;
    }
}
