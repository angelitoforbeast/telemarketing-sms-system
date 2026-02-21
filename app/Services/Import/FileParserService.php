<?php

namespace App\Services\Import;

use App\Models\ImportJob;
use App\Models\RawFlashRow;
use App\Models\RawJntRow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

class FileParserService
{
    /**
     * Store the uploaded file and create raw rows for processing.
     */
    public function parseAndStoreRawRows(UploadedFile $file, ImportJob $importJob): int
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['xlsx', 'xls'])) {
            return $this->parseExcel($file, $importJob);
        } elseif ($extension === 'csv') {
            return $this->parseCsv($file, $importJob);
        }

        throw new \RuntimeException("Unsupported file format: {$extension}. Please upload .xlsx, .xls, or .csv files.");
    }

    /**
     * Parse an Excel file (JNT typically uses .xlsx).
     */
    protected function parseExcel(UploadedFile $file, ImportJob $importJob): int
    {
        // Use a memory-efficient chunked reader for large files
        $reader = new XlsxReader();
        $reader->setReadDataOnly(true);

        // Load only the first sheet
        $worksheetNames = $reader->listWorksheetNames($file->getPathname());
        if (empty($worksheetNames)) {
            throw new \RuntimeException('The uploaded file is empty.');
        }
        $reader->setLoadSheetsOnly($worksheetNames[0]);

        $spreadsheet = $reader->load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();

        $headers = null;
        $count = 0;
        $batch = [];

        foreach ($worksheet->getRowIterator() as $rowObj) {
            $cellIterator = $rowObj->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $rowData = [];
            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getValue();
            }

            // First row is headers
            if ($headers === null) {
                $headers = array_map('trim', $rowData);
                continue;
            }

            // Pad row if shorter than headers
            while (count($rowData) < count($headers)) {
                $rowData[] = '';
            }
            // Trim row if longer than headers
            $rowData = array_slice($rowData, 0, count($headers));

            $mapped = array_combine($headers, $rowData);

            // Skip completely empty rows
            if ($this->isEmptyRow($mapped)) continue;

            $batch[] = [
                'import_job_id' => $importJob->id,
                'data' => json_encode($mapped),
                'is_processed' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $count++;

            // Insert in batches of 500 and free memory
            if (count($batch) >= 500) {
                $this->insertRawBatch($importJob->courier, $batch);
                $batch = [];
            }
        }

        // Insert remaining rows
        if (!empty($batch)) {
            $this->insertRawBatch($importJob->courier, $batch);
        }

        // Free memory
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $count;
    }

    /**
     * Parse a CSV file (Flash typically uses .csv).
     */
    protected function parseCsv(UploadedFile $file, ImportJob $importJob): int
    {
        $handle = fopen($file->getPathname(), 'r');
        if (!$handle) {
            throw new \RuntimeException('Could not open the CSV file.');
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            throw new \RuntimeException('The uploaded CSV file is empty.');
        }

        $headers = array_map('trim', $headers);
        $count = 0;
        $batch = [];

        while (($row = fgetcsv($handle)) !== false) {
            // Pad row if shorter than headers
            while (count($row) < count($headers)) {
                $row[] = '';
            }

            $rowData = array_combine($headers, $row);

            if ($this->isEmptyRow($rowData)) continue;

            $batch[] = [
                'import_job_id' => $importJob->id,
                'data' => json_encode($rowData),
                'is_processed' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $count++;

            if (count($batch) >= 500) {
                $this->insertRawBatch($importJob->courier, $batch);
                $batch = [];
            }
        }

        fclose($handle);

        if (!empty($batch)) {
            $this->insertRawBatch($importJob->courier, $batch);
        }

        return $count;
    }

    /**
     * Insert a batch of raw rows into the appropriate table.
     */
    protected function insertRawBatch(string $courier, array $batch): void
    {
        if ($courier === 'jnt') {
            RawJntRow::insert($batch);
        } else {
            RawFlashRow::insert($batch);
        }
    }

    /**
     * Check if a row is completely empty.
     */
    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (!empty(trim((string) $value))) {
                return false;
            }
        }
        return true;
    }
}
