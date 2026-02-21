<?php

namespace App\Services\Import;

use App\Models\ImportJob;
use App\Models\RawFlashRow;
use App\Models\RawJntRow;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader as XLSXReader;
use OpenSpout\Reader\CSV\Reader as CSVReader;
use OpenSpout\Reader\CSV\Options as CSVOptions;

class FileParserService
{
    const CHUNK_SIZE = 500;

    public function parseAndStoreRawRows(ImportJob $importJob): int
    {
        $filePath = Storage::disk("local")->path($importJob->storage_path);
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($extension, ["xlsx", "xls"])) {
            return $this->parseExcel($filePath, $importJob);
        } elseif ($extension === "csv") {
            return $this->parseCsv($filePath, $importJob);
        }

        throw new \RuntimeException("Unsupported file format: {$extension}.");
    }

    protected function parseExcel(string $filePath, ImportJob $importJob): int
    {
        $reader = new XLSXReader();
        $reader->open($filePath);

        $count = 0;
        $batch = [];
        $headers = null;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rowData = $row->toArray();

                if ($headers === null) {
                    $headers = array_map("trim", $rowData);
                    continue;
                }

                if (count($rowData) < count($headers)) {
                    $rowData = array_pad($rowData, count($headers), null);
                }

                $mappedData = array_combine($headers, $rowData);

                if ($this->isEmptyRow($mappedData)) continue;

                $batch[] = [
                    "import_job_id" => $importJob->id,
                    "data" => json_encode($mappedData),
                    "is_processed" => false,
                    "created_at" => now(),
                    "updated_at" => now(),
                ];

                $count++;

                if (count($batch) >= self::CHUNK_SIZE) {
                    $this->insertRawBatch($importJob->courier, $batch);
                    $batch = [];
                }
            }
        }

        if (!empty($batch)) {
            $this->insertRawBatch($importJob->courier, $batch);
        }

        $reader->close();
        return $count;
    }

    protected function parseCsv(string $filePath, ImportJob $importJob): int
    {
        $csvOptions = new CSVOptions();
        $csvOptions->FIELD_DELIMITER = ",";
        $csvOptions->FIELD_ENCLOSURE = "\"";
        $csvOptions->ENCODING = "UTF-8";

        $reader = new CSVReader($csvOptions);
        $reader->open($filePath);

        $count = 0;
        $batch = [];
        $headers = null;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rowData = $row->toArray();

                if ($headers === null) {
                    $headers = array_map("trim", $rowData);
                    continue;
                }

                if (count($rowData) < count($headers)) {
                    $rowData = array_pad($rowData, count($headers), null);
                }

                $mappedData = array_combine($headers, $rowData);

                if ($this->isEmptyRow($mappedData)) continue;

                $batch[] = [
                    "import_job_id" => $importJob->id,
                    "data" => json_encode($mappedData),
                    "is_processed" => false,
                    "created_at" => now(),
                    "updated_at" => now(),
                ];

                $count++;

                if (count($batch) >= self::CHUNK_SIZE) {
                    $this->insertRawBatch($importJob->courier, $batch);
                    $batch = [];
                }
            }
        }

        if (!empty($batch)) {
            $this->insertRawBatch($importJob->courier, $batch);
        }

        $reader->close();
        return $count;
    }

    protected function insertRawBatch(string $courier, array $batch): void
    {
        $model = $courier === "jnt" ? RawJntRow::class : RawFlashRow::class;
        $model::insert($batch);
    }

    protected function isEmptyRow(array $row): bool
    {
        return empty(array_filter($row, fn($value) => $value !== null && trim((string) $value) !== ""));
    }
}
