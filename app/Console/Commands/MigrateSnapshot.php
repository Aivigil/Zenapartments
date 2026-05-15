<?php

namespace App\Console\Commands;

use App\Enums\UnitStatus;
use App\Models\Adjustment;
use App\Models\Block;
use App\Models\Booking;
use App\Models\Client;
use App\Models\PlanTemplate;
use App\Models\Project;
use App\Models\Unit;
use App\Models\UnitCategory;
use App\Services\AuditEventWriter;
use App\Services\PlanInstantiator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

/**
 * Snapshot importer. Reads the master receivables CSV and creates, per row:
 *   - Unit  (in the Zen Apartments project, status sold)
 *   - Client (dedup on phone)
 *   - Booking (total_price = grand_total)
 *   - Forward-looking Schedule (from next month onward) via PlanInstantiator
 *   - Marks any historical (past-dated) schedule rows as 'cancelled' so they
 *     don't count toward outstanding
 *   - opening_balance Adjustment: calibrates outstanding to CSV `total_receivable`
 *   - historical_payments Adjustment: records what's already been received
 *     (so the audit log captures the pre-cutover total)
 *
 * Idempotent on apt_code. Re-running with the same CSV is safe — it updates
 * existing records and recalculates the opening balance.
 *
 * Usage:
 *   php artisan migrate:snapshot --file=migration-samples/zen-apartments-master.csv
 *   php artisan migrate:snapshot --file=... --dry-run
 *   php artisan migrate:snapshot --file=... --cutover=2026-06-01
 */
class MigrateSnapshot extends Command
{
    protected $signature = 'migrate:snapshot
                            {--file= : Path to master receivables CSV (required)}
                            {--cutover= : Cutover date — schedule rows before this date marked cancelled (default: first of next month)}
                            {--dry-run : Preview without writing to DB}';

    protected $description = 'Snapshot-import customers + bookings + opening balances from master receivables CSV.';

    public function __construct(private PlanInstantiator $planInstantiator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $file = $this->option('file');
        if (! $file || ! file_exists(base_path($file)) && ! file_exists($file)) {
            $this->error("File not found. Pass --file=migration-samples/zen-apartments-master.csv");
            return self::FAILURE;
        }

        $path = file_exists($file) ? $file : base_path($file);
        $cutover = CarbonImmutable::parse(
            $this->option('cutover') ?: CarbonImmutable::now()->addMonthNoOverflow()->startOfMonth()
        );
        $dryRun = (bool) $this->option('dry-run');

        $this->info('=== migrate:snapshot ===');
        $this->line("File:        {$path}");
        $this->line("Cutover:     {$cutover->toDateString()}");
        $this->line("Mode:        " . ($dryRun ? 'DRY RUN (no writes)' : 'LIVE'));
        $this->line('');

        // Pre-flight: resolve project + categories + plans
        $project = Project::where('code', 'ZA-KHI')->first();
        if (! $project) {
            $this->error('Project ZA-KHI not found. Run db:seed --class=ZenApartmentsProjectSeeder first.');
            return self::FAILURE;
        }
        $block = Block::where('project_id', $project->id)->where('code', 'MAIN')->first();
        $categories = UnitCategory::whereIn('code', ['ZA-STU', 'ZA-1BR', 'ZA-2BR'])->get()->keyBy('code');
        $plans = PlanTemplate::whereIn('code', ['ZA-STU-QUARTERLY-12', 'ZA-1BR-MONTHLY-48', 'ZA-2BR-QUARTERLY-11', 'ZA-CASH'])
            ->get()->keyBy('code');

        foreach (['ZA-STU', 'ZA-1BR', 'ZA-2BR'] as $c) {
            if (! isset($categories[$c])) {
                $this->error("Unit category {$c} not found. Run db:seed --class=ZenApartmentsProjectSeeder.");
                return self::FAILURE;
            }
        }
        foreach (['ZA-STU-QUARTERLY-12', 'ZA-1BR-MONTHLY-48', 'ZA-2BR-QUARTERLY-11', 'ZA-CASH'] as $p) {
            if (! isset($plans[$p])) {
                $this->error("Plan template {$p} not found. Run db:seed --class=ZenApartmentsPlansSeeder.");
                return self::FAILURE;
            }
        }

        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);
        $rows = iterator_to_array($csv->getRecords());

        $stats = ['rows' => 0, 'units_created' => 0, 'units_updated' => 0, 'clients_created' => 0, 'clients_updated' => 0, 'bookings_created' => 0, 'bookings_updated' => 0, 'cash_plans' => 0, 'refunded' => 0, 'errors' => 0];
        $headers = ['apt', 'customer', 'category', 'grand_total', 'received', 'outstanding', 'plan', 'note'];
        $preview = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                $stats['rows']++;
                $result = $this->processRow($row, $project, $block, $categories, $plans, $cutover, $stats);
                if ($result) $preview[] = $result;
            }

            if ($dryRun) {
                DB::rollBack();
                $this->warn('DRY RUN — rolled back. No changes persisted.');
            } else {
                DB::commit();
                $this->info('Committed.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Aborted: {$e->getMessage()}");
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }

        $this->line('');
        $this->info('=== Summary ===');
        $this->table(['metric', 'value'], collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->toArray());

        if (! empty($preview)) {
            $this->line('');
            $this->info('=== Per-client preview (first 15 + last 5) ===');
            $show = array_merge(array_slice($preview, 0, 15), count($preview) > 20 ? [['…','…','…','…','…','…','…','…']] : [], array_slice($preview, -5));
            $this->table($headers, $show);
        }

        return self::SUCCESS;
    }

    private function processRow(array $row, Project $project, Block $block, $categories, $plans, CarbonImmutable $cutover, array &$stats): array
    {
        $aptCode = trim($row['apt_code'] ?? '');
        if (! $aptCode) return [];

        $isRefund = stripos($row['notes'] ?? '', 'REFUND') !== false;
        if ($isRefund) $stats['refunded']++;

        $customerName = trim($row['customer_name'] ?? '');
        $phone = trim($row['phone'] ?? '');
        $categoryName = trim($row['category'] ?? '');
        $sizeSqft = (float) ($row['size_sqft'] ?? 0);
        $grandTotalMinor = money_major_to_minor((float) str_replace([',', ' '], '', $row['grand_total'] ?? '0'));
        $totalReceivedMinor = money_major_to_minor((float) str_replace([',', ' '], '', $row['total_received'] ?? '0'));
        $totalReceivableMinor = money_major_to_minor((float) str_replace([',', ' '], '', $row['total_receivable'] ?? '0'));
        $note = trim($row['notes'] ?? '');

        // Pick category
        $catCode = match (true) {
            stripos($categoryName, 'studio') !== false => 'ZA-STU',
            stripos($categoryName, '2-bed') !== false || stripos($categoryName, 'two') !== false => 'ZA-2BR',
            default => 'ZA-1BR',
        };

        // Pick plan
        $planCode = match (true) {
            $isRefund => 'ZA-CASH',
            $catCode === 'ZA-STU' => 'ZA-STU-QUARTERLY-12',
            $catCode === 'ZA-2BR' => 'ZA-2BR-QUARTERLY-11',
            default => 'ZA-1BR-MONTHLY-48',
        };

        // Heuristic: customers with received >= 80% of grand_total → effectively cash-plan
        if (! $isRefund && $grandTotalMinor > 0 && $totalReceivedMinor / $grandTotalMinor >= 0.95) {
            $planCode = 'ZA-CASH';
            $stats['cash_plans']++;
        }

        // 1. Unit (idempotent on apt_code within project)
        $unit = Unit::where('project_id', $project->id)->where('code', $aptCode)->first();
        if ($unit) {
            $unit->update([
                'name' => $customerName . ' — ' . $aptCode,
                'unit_category_id' => $categories[$catCode]->id,
                'size_value' => $sizeSqft,
                'size_unit' => 'sqft',
                'base_price_minor' => $grandTotalMinor,
                'status' => $isRefund ? UnitStatus::Cancelled->value : UnitStatus::Sold->value,
            ]);
            $stats['units_updated']++;
        } else {
            $unit = Unit::create([
                'project_id' => $project->id,
                'block_id' => $block->id,
                'unit_category_id' => $categories[$catCode]->id,
                'code' => $aptCode,
                'name' => $customerName . ' — ' . $aptCode,
                'size_value' => $sizeSqft,
                'size_unit' => 'sqft',
                'base_price_minor' => $grandTotalMinor,
                'currency' => 'PKR',
                'status' => $isRefund ? UnitStatus::Cancelled->value : UnitStatus::Sold->value,
                'attributes' => ['migrated_from' => 'OneDrive master receivables', 'imported_at' => now()->toIso8601String()],
            ]);
            $stats['units_created']++;
        }

        // 2. Client (idempotent on apt_code via the Booking — phone is unreliable for dedup)
        // We dedup on existing Booking's client; fallback to creating a new client.
        $existingBooking = Booking::where('unit_id', $unit->id)->first();
        $client = $existingBooking?->client;

        if (! $client) {
            // Try by phone
            $client = $phone ? Client::where('primary_phone', $phone)->first() : null;
        }

        if ($client) {
            $client->update([
                'full_name' => $customerName,
                'primary_phone' => $phone,
                'notes' => $note ?: $client->notes,
            ]);
            $stats['clients_updated']++;
        } else {
            $client = Client::create([
                // Temporary unique placeholder — code is NOT NULL + UNIQUE.
                // Replaced below once we have the auto-increment id.
                'code' => 'TMP-CLI-' . uniqid('', true),
                'full_name' => $customerName,
                'primary_phone' => $phone ?: '+92000000000',
                'nationality' => 'Pakistani',
                'country_of_residence' => str_starts_with($phone, '+1') ? 'CA' : (str_starts_with($phone, '+44') ? 'GB' : (str_starts_with($phone, '+971') ? 'AE' : 'PK')),
                'kyc_status' => 'pending',
                'notes' => $note,
            ]);
            $client->code = generate_code('ZA-C-', $client->id, 5);
            $client->save();
            $stats['clients_created']++;
        }

        // 3. Booking
        $bookingDate = CarbonImmutable::parse('2023-01-01'); // Synthetic date — actual is in spreadsheets
        if ($existingBooking) {
            $booking = $existingBooking;
            $booking->update([
                'client_id' => $client->id,
                'plan_template_id' => $plans[$planCode]->id,
                'total_price_minor' => $grandTotalMinor,
                'down_payment_minor' => (int) round($grandTotalMinor * 0.30),
                'status' => $isRefund ? 'cancelled' : 'active',
                'notes' => "Migrated from OneDrive. Original outstanding: " . money_format_pkr($totalReceivableMinor),
            ]);
            $stats['bookings_updated']++;
            // Wipe prior schedule + adjustments to re-run idempotently
            $booking->schedules()->delete();
            $booking->adjustments()->delete();
        } else {
            $booking = Booking::create([
                'code' => 'TMP-BKG-' . uniqid('', true),  // replaced below
                'client_id' => $client->id,
                'unit_id' => $unit->id,
                'plan_template_id' => $plans[$planCode]->id,
                'booking_date' => $bookingDate->toDateString(),
                'total_price_minor' => $grandTotalMinor,
                'down_payment_minor' => (int) round($grandTotalMinor * 0.30),
                'currency' => 'PKR',
                'status' => $isRefund ? 'cancelled' : 'active',
                'notes' => "Migrated from OneDrive on " . now()->toDateString() . ". Pre-cutover outstanding (per master): " . money_format_pkr($totalReceivableMinor),
            ]);
            $booking->code = generate_code('ZA-B-', $booking->id, 5);
            $booking->save();
            $stats['bookings_created']++;
        }

        // 4. Generate forward schedule (skip if cash plan / refund — no installments)
        if (! $isRefund && $planCode !== 'ZA-CASH') {
            $this->planInstantiator->generate($booking);
            // Cancel all historical schedule rows (due_date < cutover) so they don't count
            $booking->schedules()
                ->where('due_date', '<', $cutover->toDateString())
                ->update(['status' => 'cancelled']);
        }

        // 5. Calibrating adjustments — historical credit + opening balance
        if (! $isRefund) {
            // Mark the historical receipts as a single audit-trail credit
            if ($totalReceivedMinor > 0) {
                $adj = Adjustment::create([
                    'code' => 'TMP-ADJ-' . uniqid('', true),
                    'booking_id' => $booking->id,
                    'kind' => 'manual_credit',
                    'direction' => 'credit',
                    'amount_minor' => $totalReceivedMinor,
                    'currency' => 'PKR',
                    'effective_on' => $bookingDate->toDateString(),
                    'reason' => "Historical payments received per OneDrive master receivables (snapshot import). Pre-cutover total: " . money_format_pkr($totalReceivedMinor) . ". See OneDrive archive for itemised payment history.",
                    'requested_by' => null,
                    'approved_by' => null,
                    'approved_at' => now(),
                    'status' => 'approved',
                ]);
                $adj->code = generate_code('ZA-A-', $adj->id, 5);
                $adj->save();
            }

            // Now compute portal-implied outstanding (forward schedule only since past is cancelled) + apply
            // calibrating adjustment to match the spreadsheet's Total Receivable.
            $forwardScheduled = (int) $booking->schedules()
                ->whereNotIn('status', ['waived', 'written_off', 'cancelled'])
                ->sum('amount_minor');

            $portalOutstanding = $forwardScheduled; // no payments allocated, no other adjustments yet (we'll fix below)

            $diff = $totalReceivableMinor - $portalOutstanding;
            // diff > 0 => need to ADD outstanding (debit); diff < 0 => need to REDUCE outstanding (credit)
            if ($diff !== 0) {
                $adj = Adjustment::create([
                    'code' => 'TMP-ADJ-' . uniqid('', true),
                    'booking_id' => $booking->id,
                    'kind' => 'manual_debit',
                    'direction' => $diff > 0 ? 'debit' : 'credit',
                    'amount_minor' => abs($diff),
                    'currency' => 'PKR',
                    'effective_on' => $bookingDate->toDateString(),
                    'reason' => "Opening balance calibration (snapshot import). Reconciles forward-schedule total of " . money_format_pkr($forwardScheduled) . " to spreadsheet Total Receivable of " . money_format_pkr($totalReceivableMinor) . ".",
                    'approved_at' => now(),
                    'status' => 'approved',
                ]);
                $adj->code = generate_code('ZA-A-', $adj->id, 5);
                $adj->save();
            }
        }

        return [
            $aptCode,
            substr($customerName, 0, 30),
            $catCode,
            number_format($grandTotalMinor / 100),
            number_format($totalReceivedMinor / 100),
            number_format($totalReceivableMinor / 100),
            $planCode,
            $isRefund ? 'REFUND' : ($note ? substr($note, 0, 20) : ''),
        ];
    }
}
