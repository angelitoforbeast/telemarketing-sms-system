<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessImportJob;
use App\Models\ImportJob;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ImportController extends Controller
{
    /**
     * Show the upload form.
     */
    public function create()
    {
        return view('import.create');
    }

    /**
     * Handle file upload via AJAX → store file, create ImportJob, dispatch background job.
     * Supports .xlsx, .csv, and .zip files.
     * Returns JSON so the frontend can poll for status.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'file'    => 'required|file|max:204800', // ~200MB
                'courier' => 'required|in:jnt,flash',
            ]);

            $file = $request->file('file');
            $ext  = strtolower($file->getClientOriginalExtension());

            // Validate extension
            if (!in_array($ext, ['xlsx', 'csv', 'zip'])) {
                return response()->json([
                    'error'   => true,
                    'message' => 'Unsupported file type. Please upload .xlsx, .csv, or .zip files.',
                ], 422);
            }

            $user = $request->user();
            $folder = 'imports/' . $user->company_id . '/' . now()->format('Y-m-d');

            if ($ext === 'zip') {
                // Handle ZIP: extract and create import jobs for each file inside
                return $this->handleZipUpload($file, $request->courier, $user, $folder);
            }

            // Handle single file (.xlsx or .csv)
            return $this->handleSingleUpload($file, $request->courier, $user, $folder);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error'   => true,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Throwable $e) {
            \Log::error('Import upload error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'error'   => true,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle a single .xlsx or .csv upload.
     */
    private function handleSingleUpload($file, string $courier, $user, string $folder)
    {
        $basename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $filename = $basename . '__' . now()->format('His') . '.' . $file->getClientOriginalExtension();
        $storagePath = $file->storeAs($folder, $filename, 'local');

        $importJob = ImportJob::create([
            'company_id'        => $user->company_id,
            'user_id'           => $user->id,
            'courier'           => $courier,
            'original_filename' => $file->getClientOriginalName(),
            'storage_path'      => $storagePath,
            'status'            => 'queued',
        ]);

        ProcessImportJob::dispatch($importJob->id);

        return response()->json([
            'id'     => $importJob->id,
            'status' => $importJob->status,
        ], 201);
    }

    /**
     * Handle a .zip upload: extract, find .xlsx/.csv files, create import jobs for each.
     */
    private function handleZipUpload($file, string $courier, $user, string $folder)
    {
        // Store the zip temporarily
        $zipBasename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $zipFilename = $zipBasename . '__' . now()->format('His') . '.zip';
        $zipStoragePath = $file->storeAs($folder, $zipFilename, 'local');
        $zipAbsPath = Storage::disk('local')->path($zipStoragePath);

        $zip = new ZipArchive();
        if ($zip->open($zipAbsPath) !== true) {
            return response()->json([
                'error'   => true,
                'message' => 'Failed to open ZIP file. The file may be corrupted.',
            ], 422);
        }

        $extractDir = Storage::disk('local')->path($folder . '/extracted_' . now()->format('His'));
        $zip->extractTo($extractDir);
        $zip->close();

        // Find all .xlsx and .csv files inside extracted directory (recursive)
        $importableFiles = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            $fileExt = strtolower($fileInfo->getExtension());
            if (in_array($fileExt, ['xlsx', 'csv'])) {
                $importableFiles[] = $fileInfo->getRealPath();
            }
        }

        if (empty($importableFiles)) {
            return response()->json([
                'error'   => true,
                'message' => 'No .xlsx or .csv files found inside the ZIP archive.',
            ], 422);
        }

        $importJobIds = [];

        foreach ($importableFiles as $absFilePath) {
            $originalName = basename($absFilePath);
            $fileExt = strtolower(pathinfo($absFilePath, PATHINFO_EXTENSION));

            // Move file to storage
            $storedName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME))
                . '__' . now()->format('His') . '_' . Str::random(4) . '.' . $fileExt;
            $storedRelPath = $folder . '/' . $storedName;
            $storedAbsPath = Storage::disk('local')->path($storedRelPath);

            // Ensure target directory exists
            $targetDir = dirname($storedAbsPath);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            copy($absFilePath, $storedAbsPath);

            $importJob = ImportJob::create([
                'company_id'        => $user->company_id,
                'user_id'           => $user->id,
                'courier'           => $courier,
                'original_filename' => $originalName,
                'storage_path'      => $storedRelPath,
                'status'            => 'queued',
            ]);

            ProcessImportJob::dispatch($importJob->id);
            $importJobIds[] = $importJob->id;
        }

        // Clean up extracted directory
        $this->deleteDirectory($extractDir);

        // Return the first import job id for polling, plus total count
        return response()->json([
            'id'          => $importJobIds[0],
            'status'      => 'queued',
            'zip'         => true,
            'total_files' => count($importJobIds),
            'job_ids'     => $importJobIds,
        ], 201);
    }

    /**
     * Recursively delete a directory.
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }
        rmdir($dir);
    }

    /**
     * AJAX polling endpoint — returns current status of an import job.
     */
    public function status(ImportJob $importJob)
    {
        if ($importJob->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        return response()->json([
            'id'              => $importJob->id,
            'status'          => $importJob->status,
            'processed_rows'  => $importJob->processed_rows ?? 0,
            'total_rows'      => $importJob->total_rows,
            'new_shipments'   => $importJob->new_shipments_count ?? 0,
            'updated'         => $importJob->updated_shipments_count ?? 0,
            'skipped'         => $importJob->skipped_count ?? 0,
            'failed_rows'     => $importJob->failed_rows_count ?? 0,
            'error_summary'   => $importJob->error_summary,
            'started_at'      => optional($importJob->started_at)?->toDateTimeString(),
            'completed_at'    => optional($importJob->completed_at)?->toDateTimeString(),
        ]);
    }

    /**
     * AJAX polling endpoint for multiple import jobs (ZIP upload).
     */
    public function statusBatch(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['jobs' => []]);
        }

        $jobs = ImportJob::whereIn('id', $ids)
            ->where('company_id', auth()->user()->company_id)
            ->get()
            ->map(function ($job) {
                return [
                    'id'              => $job->id,
                    'status'          => $job->status,
                    'original_filename' => $job->original_filename,
                    'processed_rows'  => $job->processed_rows ?? 0,
                    'total_rows'      => $job->total_rows,
                    'new_shipments'   => $job->new_shipments_count ?? 0,
                    'updated'         => $job->updated_shipments_count ?? 0,
                    'skipped'         => $job->skipped_count ?? 0,
                    'failed_rows'     => $job->failed_rows_count ?? 0,
                ];
            });

        // Aggregate stats
        $allCompleted = $jobs->every(fn($j) => in_array($j['status'], ['completed', 'failed']));
        $anyFailed = $jobs->contains(fn($j) => $j['status'] === 'failed');

        return response()->json([
            'jobs'           => $jobs,
            'all_completed'  => $allCompleted,
            'any_failed'     => $anyFailed,
            'total_processed' => $jobs->sum('processed_rows'),
            'total_new'      => $jobs->sum('new_shipments'),
            'total_updated'  => $jobs->sum('updated'),
            'total_skipped'  => $jobs->sum('skipped'),
        ]);
    }

    /**
     * Show a single import job detail page.
     */
    public function show(ImportJob $importJob)
    {
        if ($importJob->company_id !== auth()->user()->company_id) {
            abort(403);
        }
        $importJob->load('user');
        return view('import.show', compact('importJob'));
    }

    /**
     * List all import jobs for the company.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $importJobs = ImportJob::forCompany($user->company_id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('import.index', compact('importJobs'));
    }
}
