<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessImportJob;
use App\Models\ImportJob;
use App\Services\Import\ImportService;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(
        protected ImportService $importService
    ) {}

    public function create()
    {
        return view("import.create");
    }

    public function store(Request $request)
    {
        $request->validate([
            "file" => "required|file|mimes:xlsx,xls,csv|max:51200", // 50MB max
            "courier" => "required|in:jnt,flash",
        ]);

        $file = $request->file("file");
        $user = $request->user();

        // Store the file
        $storagePath = $file->store("imports/" . $user->company_id, "local");

        // Create the import job record
        $importJob = ImportJob::create([
            "company_id" => $user->company_id,
            "user_id" => $user->id,
            "courier" => $request->courier,
            "original_filename" => $file->getClientOriginalName(),
            "storage_path" => $storagePath,
            "status" => "queued",
        ]);

        // Dispatch the background processing job
        ProcessImportJob::dispatch($importJob);

        return redirect()->route("import.show", $importJob)
            ->with("success", "File uploaded successfully and is now queued for processing. You will be notified upon completion.");
    }

    public function show(ImportJob $importJob)
    {
        $this->authorizeCompany($importJob);
        $importJob->load("user");
        return view("import.show", compact("importJob"));
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $importJobs = ImportJob::forCompany($user->company_id)
            ->with("user")
            ->orderBy("created_at", "desc")
            ->paginate(20);

        return view("import.index", compact("importJobs"));
    }

    protected function authorizeCompany(ImportJob $importJob): void
    {
        if ($importJob->company_id !== auth()->user()->company_id) {
            abort(403);
        }
    }
}
