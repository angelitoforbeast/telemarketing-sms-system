<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessImportJob;
use App\Models\ImportJob;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
     * Returns JSON so the frontend can poll for status.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'file'    => 'required|file|mimes:xlsx,csv|max:204800', // ~200MB
                'courier' => 'required|in:jnt,flash',
            ]);

            $file = $request->file('file');
            $user = $request->user();

            // 1) Persist file to storage (local disk)
            $folder   = 'imports/' . $user->company_id . '/' . now()->format('Y-m-d');
            $basename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $filename = $basename . '__' . now()->format('His') . '.' . $file->getClientOriginalExtension();
            $storagePath = $file->storeAs($folder, $filename, 'local');

            // 2) Create ImportJob record (status: queued)
            $importJob = ImportJob::create([
                'company_id'        => $user->company_id,
                'user_id'           => $user->id,
                'courier'           => $request->courier,
                'original_filename' => $file->getClientOriginalName(),
                'storage_path'      => $storagePath,
                'status'            => 'queued',
            ]);

            // 3) Dispatch background job
            ProcessImportJob::dispatch($importJob->id);

            // 4) Return JSON (frontend will poll /status)
            return response()->json([
                'id'     => $importJob->id,
                'status' => $importJob->status,
                'path'   => $importJob->storage_path,
            ], 201);

        } catch (\Throwable $e) {
            \Log::error('Import upload error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'error'   => true,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * AJAX polling endpoint — returns current status of an import job.
     */
    public function status(ImportJob $importJob)
    {
        // Company scope check
        if ($importJob->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        return response()->json([
            'id'              => $importJob->id,
            'status'          => $importJob->status,       // queued|processing|completed|failed
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
