<?php

namespace App\Services\Reconciliation;

use App\Models\BankStatementImport;
use App\Models\BankStatementLine;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

/**
 * Parses a bank-statement CSV into BankStatementImport + line rows.
 *
 * Accepts a flexible mapping so different banks' exports work. Defaults
 * match a common Pakistani-bank export format:
 *   Transaction Date, Description, Debit, Credit, Reference, Balance
 *
 * Override by passing a map array on `import()`:
 *   ['date' => 'Txn Date', 'description' => 'Narration',
 *    'amount' => 'Credit', 'reference' => 'Reference No']
 */
class CsvImporter
{
    public function import(
        UploadedFile $file,
        string $bankAccount,
        array $colMap = [],
        ?string $importLabel = null
    ): BankStatementImport {
        $colMap = array_merge([
            'date' => 'Transaction Date',
            'description' => 'Description',
            'credit' => 'Credit',
            'debit' => 'Debit',
            'amount' => null,        // if your bank gives a signed amount column
            'reference' => 'Reference',
            'counterparty' => 'Counterparty',
        ], $colMap);

        $csv = Reader::createFromPath($file->getRealPath(), 'r');
        $csv->setHeaderOffset(0);
        $rows = iterator_to_array($csv->getRecords());

        $hash = hash_file('sha256', $file->getRealPath());
        $periodStart = null;
        $periodEnd = null;
        $parsed = [];

        foreach ($rows as $row) {
            $rawDate = $row[$colMap['date']] ?? null;
            if (! $rawDate) continue;
            try {
                $date = CarbonImmutable::parse($rawDate);
            } catch (\Throwable $e) {
                continue;
            }

            // Resolve direction + amount
            if ($colMap['amount'] && isset($row[$colMap['amount']])) {
                $amt = $this->parseMoney($row[$colMap['amount']]);
                $direction = $amt >= 0 ? 'credit' : 'debit';
                $amountMinor = abs($amt);
            } else {
                $creditStr = $row[$colMap['credit']] ?? '';
                $debitStr  = $row[$colMap['debit']]  ?? '';
                $credit = $this->parseMoney($creditStr);
                $debit  = $this->parseMoney($debitStr);
                if ($credit > 0) {
                    $direction = 'credit';
                    $amountMinor = $credit;
                } elseif ($debit > 0) {
                    $direction = 'debit';
                    $amountMinor = $debit;
                } else {
                    continue; // unknown row
                }
            }

            $parsed[] = [
                'txn_date' => $date->toDateString(),
                'direction' => $direction,
                'amount_minor' => $amountMinor,
                'description' => $row[$colMap['description']] ?? null,
                'reference' => $row[$colMap['reference']] ?? null,
                'counterparty' => $row[$colMap['counterparty']] ?? null,
                'raw' => $row,
            ];

            if (! $periodStart || $date->lt($periodStart)) $periodStart = $date;
            if (! $periodEnd   || $date->gt($periodEnd))   $periodEnd   = $date;
        }

        if (empty($parsed)) {
            throw new \RuntimeException('No usable rows found in CSV.');
        }

        return DB::transaction(function () use ($parsed, $bankAccount, $hash, $periodStart, $periodEnd, $file, $importLabel) {
            $import = BankStatementImport::create([
                'bank_account' => $bankAccount,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'source_filename' => $importLabel ?: $file->getClientOriginalName(),
                'source_hash' => $hash,
                'total_lines' => count($parsed),
                'imported_by' => Auth::id(),
            ]);

            foreach ($parsed as $row) {
                BankStatementLine::create(array_merge($row, [
                    'bank_statement_import_id' => $import->id,
                    'bank_account' => $bankAccount,
                    'currency' => 'PKR',
                    'status' => 'pending',
                ]));
            }

            return $import->fresh();
        });
    }

    /** Parse a money-ish string ("Rs. 1,250.50", "1250.50", "1,250") to minor units. */
    private function parseMoney(string|int|float|null $value): int
    {
        if ($value === null || $value === '') return 0;
        $clean = preg_replace('/[^\d.\-]/', '', (string) $value);
        if ($clean === '' || $clean === '-' || $clean === '.') return 0;
        return (int) round(((float) $clean) * 100);
    }
}
