<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Project;
use App\Models\Unit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use League\Csv\Reader;

class OpeningBalancesController extends Controller
{
    public function show(Request $request): Response
    {
        $request->user()->can('reports.view') || abort(403);

        // Default to the committed master CSV in the repo
        $file = $request->input('file', base_path('migration-samples/zen-apartments-master.csv'));
        $fileExists = file_exists($file);

        $rows = [];
        $totals = ['expected_minor' => 0, 'portal_minor' => 0, 'clean' => 0, 'flagged' => 0, 'missing' => 0];

        if ($fileExists) {
            $project = Project::where('code', 'ZA-KHI')->first();

            $csv = Reader::createFromPath($file, 'r');
            $csv->setHeaderOffset(0);
            foreach ($csv->getRecords() as $row) {
                $aptCode = trim($row['apt_code'] ?? '');
                if (! $aptCode) continue;

                $expected = (int) round(((float) str_replace([',', ' '], '', $row['total_receivable'] ?? '0')) * 100);
                $unit = $project ? Unit::where('project_id', $project->id)->where('code', $aptCode)->first() : null;
                $booking = $unit ? Booking::where('unit_id', $unit->id)->first() : null;

                $portal = $booking?->outstandingMinor();
                $variance = $portal !== null ? $portal - $expected : null;

                $status = match (true) {
                    $portal === null => 'missing',
                    $variance === 0 => 'clean',
                    default => 'flagged',
                };

                $totals['expected_minor'] += $expected;
                if ($portal !== null) $totals['portal_minor'] += $portal;
                $totals[$status]++;

                $rows[] = [
                    'apt_code' => $aptCode,
                    'customer' => trim($row['customer_name'] ?? ''),
                    'category' => trim($row['category'] ?? ''),
                    'booking_id' => $booking?->id,
                    'booking_code' => $booking?->code,
                    'expected_minor' => $expected,
                    'portal_minor' => $portal,
                    'variance_minor' => $variance,
                    'status' => $status,
                ];
            }

            // Sort: flagged first, then missing, then clean
            $sortOrder = ['flagged' => 0, 'missing' => 1, 'clean' => 2];
            usort($rows, fn ($a, $b) => $sortOrder[$a['status']] <=> $sortOrder[$b['status']]);
        }

        return Inertia::render('Reports/OpeningBalances', [
            'rows' => $rows,
            'totals' => $totals,
            'file' => $file,
            'file_exists' => $fileExists,
        ]);
    }
}
