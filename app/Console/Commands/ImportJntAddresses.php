<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportJntAddresses extends Command
{
    protected $signature = 'import:jnt-addresses {file? : Path to the JNT address text file}';
    protected $description = 'Import J&T address data (Province|City|Barangay) into jnt_addresses table';

    public function handle(): int
    {
        $file = $this->argument('file') ?? '/tmp/jnt_address.txt';

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $this->info("Reading {$file}...");
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $total = count($lines);
        $this->info("Found {$total} address entries.");

        // Truncate existing data
        DB::table('jnt_addresses')->truncate();
        $this->info("Cleared existing jnt_addresses data.");

        // Batch insert for performance
        $batch = [];
        $batchSize = 500;
        $inserted = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($lines as $line) {
            $parts = explode('|', trim($line));
            if (count($parts) !== 3) {
                $bar->advance();
                continue;
            }

            $batch[] = [
                'province' => trim($parts[0]),
                'city' => trim($parts[1]),
                'barangay' => trim($parts[2]),
            ];

            if (count($batch) >= $batchSize) {
                DB::table('jnt_addresses')->insert($batch);
                $inserted += count($batch);
                $batch = [];
            }

            $bar->advance();
        }

        // Insert remaining
        if (!empty($batch)) {
            DB::table('jnt_addresses')->insert($batch);
            $inserted += count($batch);
        }

        $bar->finish();
        $this->newLine();

        // Show stats
        $provinces = DB::table('jnt_addresses')->distinct()->count('province');
        $cities = DB::table('jnt_addresses')->distinct()->count('city');
        $this->info("Import complete: {$inserted} entries ({$provinces} provinces, {$cities} cities)");

        return 0;
    }
}
