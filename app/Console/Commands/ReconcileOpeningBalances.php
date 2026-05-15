<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Project;
use App\Models\Unit;
use Illuminate\Console\Command;
use League\Csv\Reader;
use League\Csv\Writer;

/**
 * Compares portal-computed outstanding (via Booking::outstandingMinor) against
 * the spreadsheet Total Receivable per row in the master CSV. Outputs a
 * variance table to stdout AND optionally to a CSV file for sharing.
 *
 * Usage:
 *   php artisan reconcile:opening-balances --file=migration-samples/zen-apartments-master.csv
 *   php artisan reconcile:opening-balances --file=... --out=storage/app/opening-balances.csv
 */
class ReconcileOpeningBalances extends Command
{
    protected $signature = 'reconcile:opening-balances
                            {--file= : Path to master receivables CSV (required)}
                            {--out= : Optional path to write a variance CSV}
                            {--threshold=0 : Only flag rows with absolute variance ≥ this many PKR (default 0)}';

    protected $description = 'Per-client variance: portal outstanding vs spreadsheet Total Receivable.';

    public function handle(): int
    {
        $file = $this->option('file');
        if (! $file) {
            $this->error('Pass --file=...');
            return self::FAILURE;
        }
        $path = file_exists($file) ? $file : base_path($file);
        if (! file_exists($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        $threshold = (int) ($this->option('threshold') ?: 0);
        $project = Project::where('code', 'ZA-KHI')->first();
        if (! $project) {
            $this->error('Project ZA-KHI not found.');
            return self::FAILURE;
        }

        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);
        $rows = iterator_to_array($csv->getRecords());

        $results = [];
        $clean = 0; $flagged = 0; $missing = 0;
        $totalSpreadsheet = 0; $totalPortal = 0;

        foreach ($rows as $row) {
            $aptCode = trim($row['apt_code'] ?? '');
            if (! $aptCode) continue;

            $unit = Unit::where('project_id', $project->id)->where('code', $aptCode)->first();
            $booking = $unit ? Booking::where('unit_id', $unit->id)->first() : null;

            $expected = (int) round(((float) str_replace([',', ' '], '', $row['total_receivable'] ?? '0')) * 100);

            if (! $booking) {
                $results[] = [
                    'apt_code' => $aptCode,
                    'customer' => substr(trim($row['customer_name'] ?? ''), 0, 30),
                    'expected' => number_format($expected / 100),
                    'portal' => '— NOT IN PORTAL —',
                    'variance' => '—',
                    'status' => '⚠ MISSING',
                ];
                $missing++;
                continue;
            }

            $portal = $booking->outstandingMinor();
            $variance = $portal - $expected;

            $totalSpreadsheet += $expected;
            $totalPortal += $portal;

            $isClean = abs($variance) <= $threshold;
            if ($isClean) $clean++; else $flagged++;

            $results[] = [
                'apt_code' => $aptCode,
                'customer' => substr(trim($row['customer_name'] ?? ''), 0, 30),
                'expected' => number_format($expected / 100),
                'portal' => number_format($portal / 100),
                'variance' => ($variance > 0 ? '+' : '') . number_format($variance / 100),
                'status' => $isClean ? '✓' : '⚠ DIFF',
            ];
        }

        $this->info('=== Opening-balance variance report ===');
        $this->table(['Apt', 'Customer', 'Expected (PKR)', 'Portal (PKR)', 'Variance', 'Status'], $results);

        $this->line('');
        $this->info('=== Totals ===');
        $this->line(sprintf('Clean (variance = 0):    %3d', $clean));
        $this->line(sprintf('Flagged (variance ≠ 0):  %3d', $flagged));
        $this->line(sprintf('Missing in portal:       %3d', $missing));
        $this->line('');
        $this->line(sprintf('Spreadsheet total: %s', money_format_pkr($totalSpreadsheet)));
        $this->line(sprintf('Portal total:      %s', money_format_pkr($totalPortal)));
        $this->line(sprintf('Net variance:      %s', money_format_pkr($totalPortal - $totalSpreadsheet)));

        if ($out = $this->option('out')) {
            $writer = Writer::createFromPath($out, 'w+');
            $writer->insertOne(['apt_code', 'customer', 'expected_pkr', 'portal_pkr', 'variance_pkr', 'status']);
            foreach ($results as $r) {
                $writer->insertOne(array_values($r));
            }
            $this->info("CSV written to {$out}");
        }

        return $flagged === 0 && $missing === 0 ? self::SUCCESS : self::FAILURE;
    }
}
