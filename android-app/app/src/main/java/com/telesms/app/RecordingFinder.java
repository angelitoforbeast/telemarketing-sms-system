package com.telesms.app;

import android.content.Context;
import android.content.SharedPreferences;
import android.os.Environment;
import android.util.Log;

import java.io.File;
import java.io.FileFilter;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

/**
 * Scans known call recording directories across different phone brands
 * to find the most recent recording after a call ends.
 *
 * Search strategy (in order):
 * 1. Check cached recording path from previous successful find
 * 2. Scan known brand-specific directories (flat files)
 * 3. Look for phone-number-named subfolders and scan inside them
 * 4. Fallback to most-recently-modified subfolder
 * 5. Limited deep scan as last resort
 */
public class RecordingFinder {

    private static final String TAG = "RecordingFinder";
    private static final String PREF_NAME = "telesms_recording";
    private static final String PREF_CACHED_PATH = "cached_recording_dir";

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
     * Uses a smart multi-step strategy to find the file quickly.
     *
     * @param callStartTime  Timestamp when the call started (millis)
     * @param phoneNumber    The phone number that was called (for matching)
     * @param context        App context for caching the successful path
     * @return The most recent recording file, or null if not found
     */
    public static File findLatestRecording(long callStartTime, String phoneNumber, Context context) {
        File storageRoot = Environment.getExternalStorageDirectory();
        String cleanNumber = cleanPhoneNumber(phoneNumber);
        List<File> candidates = new ArrayList<>();

        // ── Step 1: Check cached recording directory first ──
        File cachedResult = scanCachedDirectory(context, callStartTime, cleanNumber);
        if (cachedResult != null) {
            Log.i(TAG, "Found recording via cached path: " + cachedResult.getAbsolutePath());
            return cachedResult;
        }

        // ── Step 2: Scan known brand directories (flat files + phone number subfolders) ──
        for (String path : RECORDING_PATHS) {
            File dir = new File(storageRoot, path);
            if (!dir.exists() || !dir.isDirectory()) continue;

            Log.d(TAG, "Scanning directory: " + dir.getAbsolutePath());

            // 2a: Scan flat files directly in this directory
            scanDirectoryFiltered(dir, callStartTime, cleanNumber, candidates);

            if (!candidates.isEmpty()) {
                File best = pickBestCandidate(candidates, cleanNumber);
                cacheDirectory(context, dir.getAbsolutePath());
                return best;
            }

            // 2b: Look for phone-number-named subfolder
            File numberFolder = findPhoneNumberFolder(dir, cleanNumber);
            if (numberFolder != null) {
                Log.d(TAG, "Found phone number folder: " + numberFolder.getAbsolutePath());
                scanDirectoryFiltered(numberFolder, callStartTime, cleanNumber, candidates);

                if (!candidates.isEmpty()) {
                    File best = pickBestCandidate(candidates, cleanNumber);
                    cacheDirectory(context, numberFolder.getAbsolutePath());
                    return best;
                }
            }

            // 2c: Fallback — check most recently modified subfolder
            File recentFolder = findMostRecentSubfolder(dir, callStartTime);
            if (recentFolder != null && (numberFolder == null || !recentFolder.equals(numberFolder))) {
                Log.d(TAG, "Checking most recent subfolder: " + recentFolder.getAbsolutePath());
                scanDirectoryFiltered(recentFolder, callStartTime, cleanNumber, candidates);

                if (!candidates.isEmpty()) {
                    File best = pickBestCandidate(candidates, cleanNumber);
                    cacheDirectory(context, recentFolder.getAbsolutePath());
                    return best;
                }
            }
        }

        // ── Step 3: Limited deep scan as last resort ──
        Log.d(TAG, "No recording found in known paths. Doing limited deep scan...");
        deepScan(storageRoot, callStartTime, cleanNumber, candidates, 0);

        if (!candidates.isEmpty()) {
            File best = pickBestCandidate(candidates, cleanNumber);
            // Cache the parent directory of the found file
            cacheDirectory(context, best.getParentFile().getAbsolutePath());
            return best;
        }

        Log.w(TAG, "No recording found after call at " + callStartTime);
        return null;
    }

    /**
     * Overload for backward compatibility (without context).
     */
    public static File findLatestRecording(long callStartTime, String phoneNumber) {
        return findLatestRecording(callStartTime, phoneNumber, null);
    }

    // ── Scanning Methods ──

    /**
     * Scan a directory using FileFilter for efficiency.
     * Only loads files that match our criteria (audio, recent, minimum size).
     */
    private static void scanDirectoryFiltered(File dir, long callStartTime, String cleanNumber, List<File> candidates) {
        final long minTime = callStartTime - 5000; // 5 second buffer

        // Use FileFilter — OS filters before returning, much faster than loading all files
        File[] matchingFiles = dir.listFiles(new FileFilter() {
            @Override
            public boolean accept(File file) {
                return file.isFile()
                        && isAudioFile(file)
                        && file.lastModified() >= minTime
                        && file.length() > 1000; // At least 1KB
            }
        });

        if (matchingFiles == null || matchingFiles.length == 0) return;

        for (File file : matchingFiles) {
            Log.d(TAG, "Candidate: " + file.getName() +
                    " (modified: " + file.lastModified() + ", size: " + file.length() + ")");

            // Check if filename contains the phone number — high confidence match
            if (cleanNumber != null && !cleanNumber.isEmpty() && fileNameContainsNumber(file, cleanNumber)) {
                Log.d(TAG, "Phone number match in filename!");
                candidates.add(0, file); // Add to front (highest priority)
            } else {
                candidates.add(file);
            }
        }
    }

    /**
     * Find a subfolder whose name contains the phone number.
     * Tries multiple variations: full number, last 10 digits, last 7 digits.
     */
    private static File findPhoneNumberFolder(File parentDir, String cleanNumber) {
        if (cleanNumber == null || cleanNumber.isEmpty()) return null;

        // Get subfolders only (not files)
        File[] subfolders = parentDir.listFiles(File::isDirectory);
        if (subfolders == null || subfolders.length == 0) return null;

        // Generate number variants to match against folder names
        String[] variants = getNumberVariants(cleanNumber);

        for (File folder : subfolders) {
            String folderName = folder.getName().replaceAll("[^0-9]", "");
            if (folderName.isEmpty()) continue;

            for (String variant : variants) {
                if (folderName.contains(variant) || variant.contains(folderName)) {
                    return folder;
                }
            }
        }

        return null;
    }

    /**
     * Find the most recently modified subfolder in a directory.
     * This catches cases where the phone creates a new folder for each call.
     */
    private static File findMostRecentSubfolder(File parentDir, long callStartTime) {
        File[] subfolders = parentDir.listFiles(File::isDirectory);
        if (subfolders == null || subfolders.length == 0) return null;

        File mostRecent = null;
        long mostRecentTime = 0;

        for (File folder : subfolders) {
            // Only consider folders modified around or after the call started
            if (folder.lastModified() >= callStartTime - 10000) { // 10 second buffer
                if (folder.lastModified() > mostRecentTime) {
                    mostRecentTime = folder.lastModified();
                    mostRecent = folder;
                }
            }
        }

        // If no folder was modified around call time, just get the newest one
        if (mostRecent == null) {
            for (File folder : subfolders) {
                if (folder.lastModified() > mostRecentTime) {
                    mostRecentTime = folder.lastModified();
                    mostRecent = folder;
                }
            }
        }

        return mostRecent;
    }

    /**
     * Scan cached directory from a previous successful find.
     */
    private static File scanCachedDirectory(Context context, long callStartTime, String cleanNumber) {
        if (context == null) return null;

        SharedPreferences prefs = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE);
        String cachedPath = prefs.getString(PREF_CACHED_PATH, "");

        if (cachedPath.isEmpty()) return null;

        File cachedDir = new File(cachedPath);
        if (!cachedDir.exists() || !cachedDir.isDirectory()) {
            // Cached path no longer valid, clear it
            prefs.edit().remove(PREF_CACHED_PATH).apply();
            return null;
        }

        Log.d(TAG, "Trying cached directory: " + cachedPath);
        List<File> candidates = new ArrayList<>();

        // Scan the cached directory itself
        scanDirectoryFiltered(cachedDir, callStartTime, cleanNumber, candidates);

        if (!candidates.isEmpty()) {
            return pickBestCandidate(candidates, cleanNumber);
        }

        // Also check phone number subfolder within cached directory's parent
        File parent = cachedDir.getParentFile();
        if (parent != null && parent.exists()) {
            File numberFolder = findPhoneNumberFolder(parent, cleanNumber);
            if (numberFolder != null) {
                scanDirectoryFiltered(numberFolder, callStartTime, cleanNumber, candidates);
                if (!candidates.isEmpty()) {
                    cacheDirectory(context, numberFolder.getAbsolutePath());
                    return pickBestCandidate(candidates, cleanNumber);
                }
            }
        }

        return null;
    }

    /**
     * Limited deep scan — only enters directories with recording-related names.
     * Max depth of 3 to prevent excessive scanning.
     */
    private static void deepScan(File dir, long callStartTime, String cleanNumber,
                                  List<File> candidates, int depth) {
        if (depth > 3) return;
        if (dir == null || !dir.exists()) return;

        File[] entries = dir.listFiles();
        if (entries == null) return;

        final long minTime = callStartTime - 5000;

        for (File entry : entries) {
            if (entry.isDirectory()) {
                String name = entry.getName().toLowerCase();
                // Only scan directories that might contain recordings
                if (name.contains("record") || name.contains("call") ||
                    name.contains("sound") || name.contains("audio") ||
                    name.contains("voice") || name.contains("miui")) {
                    deepScan(entry, callStartTime, cleanNumber, candidates, depth + 1);
                }
            } else if (entry.isFile() && isAudioFile(entry) &&
                       entry.lastModified() >= minTime &&
                       entry.length() > 1000) {
                candidates.add(entry);
            }
        }
    }

    // ── Helper Methods ──

    /**
     * Pick the best candidate from the list.
     * Prefers files with phone number in the name, then most recent.
     */
    private static File pickBestCandidate(List<File> candidates, String cleanNumber) {
        if (candidates.isEmpty()) return null;

        // First: try to find one with phone number in filename
        if (cleanNumber != null && !cleanNumber.isEmpty()) {
            for (File file : candidates) {
                if (fileNameContainsNumber(file, cleanNumber)) {
                    Log.i(TAG, "Best candidate (number match): " + file.getAbsolutePath() +
                            " (size: " + file.length() + " bytes)");
                    return file;
                }
            }
        }

        // Second: sort by lastModified descending, pick newest
        candidates.sort((a, b) -> Long.compare(b.lastModified(), a.lastModified()));

        File best = candidates.get(0);
        Log.i(TAG, "Best candidate (most recent): " + best.getAbsolutePath() +
                " (modified: " + best.lastModified() + ", size: " + best.length() + " bytes)");
        return best;
    }

    /**
     * Check if a filename contains any variant of the phone number.
     */
    private static boolean fileNameContainsNumber(File file, String cleanNumber) {
        String fileName = file.getName().replaceAll("[^0-9]", "");
        if (fileName.isEmpty()) return false;

        String[] variants = getNumberVariants(cleanNumber);
        for (String variant : variants) {
            if (fileName.contains(variant)) return true;
        }
        return false;
    }

    /**
     * Generate phone number variants for matching.
     * E.g., "09171234567" -> ["09171234567", "9171234567", "1234567"]
     */
    private static String[] getNumberVariants(String cleanNumber) {
        if (cleanNumber == null || cleanNumber.isEmpty()) return new String[0];

        List<String> variants = new ArrayList<>();
        variants.add(cleanNumber); // Full number

        // Last 10 digits (without leading 0 or country code)
        if (cleanNumber.length() > 10) {
            variants.add(cleanNumber.substring(cleanNumber.length() - 10));
        }

        // Last 7 digits (local number without area code)
        if (cleanNumber.length() > 7) {
            variants.add(cleanNumber.substring(cleanNumber.length() - 7));
        }

        return variants.toArray(new String[0]);
    }

    /**
     * Clean phone number — remove all non-digit characters.
     */
    private static String cleanPhoneNumber(String phoneNumber) {
        if (phoneNumber == null || phoneNumber.isEmpty()) return "";
        return phoneNumber.replaceAll("[^0-9]", "");
    }

    /**
     * Cache a successful recording directory path for faster future lookups.
     */
    private static void cacheDirectory(Context context, String path) {
        if (context == null || path == null) return;
        SharedPreferences prefs = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE);
        prefs.edit().putString(PREF_CACHED_PATH, path).apply();
        Log.d(TAG, "Cached recording directory: " + path);
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

                // Also list subfolders (phone number folders)
                File[] subfolders = dir.listFiles(File::isDirectory);
                if (subfolders != null) {
                    for (File sub : subfolders) {
                        activePaths.add(sub.getAbsolutePath());
                    }
                }
            }
        }
        return activePaths;
    }
}
