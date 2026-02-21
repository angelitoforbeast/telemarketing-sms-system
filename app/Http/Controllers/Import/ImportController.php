<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessImportJob;
use App\Models\ImportJob;
use App\Services\Import\FileParserService;
use App\Services\Import\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    public function __construct(
        protected FileParserService $fileParser,
        protected ImportService $importService
    ) {}

    /**
     * Show the import upload form.
     */
    public function create()
    {
        return view('import.create');
    }

    /**
     * Handle the file upload and dispatch the import job.
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:51200', // 50MB max
            'courier' => 'required|in:jnt,flash',
        ]);

        $file = $request->file('file');
        $user = $request->user();

        // Validate file headers match selected courier
        $detectedCourier = $this->detectCourierFromFile($file);
        if ($detectedCourier && $detectedCourier !== $request->courier) {
            return back()->withErrors([
                'file' => "The uploaded file appears to be a {$detectedCourier} file, but you selected {$request->courier}. Please check and try again.",
            ])->withInput();
        }

        // Store the file
        $storagePath = $file->store('imports/' . $user->company_id, 'local');

        // Create the import job record
        $importJob = ImportJob::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'courier' => $request->courier,
            'original_filename' => $file->getClientOriginalName(),
            'storage_path' => $storagePath,
            'status' => 'queued',
        ]);

        // Parse file and store raw rows
        try {
            $totalRows = $this->fileParser->parseAndStoreRawRows($file, $importJob);
            $importJob->update(['total_rows' => $totalRows]);
        } catch (\Throwable $e) {
            $importJob->markFailed(['message' => $e->getMessage()]);
            return back()->withErrors(['file' => 'Failed to parse file: ' . $e->getMessage()]);
        }

        // Dispatch the background processing job
        ProcessImportJob::dispatch($importJob);

        return redirect()->route('import.show', $importJob)
            ->with('success', "File uploaded successfully. {$totalRows} rows are being processed.");
    }

    /**
     * Show import job status and results.
     */
    public function show(ImportJob $importJob)
    {
        $this->authorizeCompany($importJob);

        $importJob->load('user');

        return view('import.show', compact('importJob'));
    }

    /**
     * List all import jobs for the current company.
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

    /**
     * Try to detect courier type from file headers.
     */
    protected function detectCourierFromFile($file): ?string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        try {
            if (in_array($extension, ['xlsx', 'xls'])) {
                // Read only the first row for header detection (memory efficient)
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                $reader->setReadDataOnly(true);

                // Use a read filter that only loads row 1
                $filter = new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
                    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                    {
                        return $row === 1;
                    }
                };
                $reader->setReadFilter($filter);

                $spreadsheet = $reader->load($file->getPathname());
                $headers = $spreadsheet->getActiveSheet()->toArray()[0] ?? [];
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
            } else {
                $handle = fopen($file->getPathname(), 'r');
                $headers = fgetcsv($handle) ?: [];
                fclose($handle);
            }

            $headers = array_map('trim', $headers);
            return $this->importService->detectCourier($headers);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function authorizeCompany(ImportJob $importJob): void
    {
        if ($importJob->company_id !== auth()->user()->company_id) {
            abort(403);
        }
    }
}
