<?php

namespace App\Services\Reconciliation;

use App\Models\BankStatementLine;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Schedule;

/**
 * Given a BankStatementLine (credit), suggest the most likely client +
 * schedule item it should be allocated to.
 *
 * Heuristics, in order of score weight:
 *   1. Client code (ZR-C-NNNNN) literally appears in the description → 80 pts
 *   2. Bank reference matches a known Payment.bank_reference → 80 pts
 *   3. Exact amount matches an open schedule item due ±7 days → 50 pts
 *   4. Counterparty name fuzzy-matches a client's full_name → 30 pts
 *   5. Phone number digits in description match a client's primary_phone → 30 pts
 *
 * Top N matches (with score breakdown) are written to suggested_matches JSON.
 */
class SuggestedMatcher
{
    public function suggestFor(BankStatementLine $line, int $limit = 5): array
    {
        // Only credit (incoming) lines need matching
        if ($line->direction !== 'credit') return [];

        $haystack = strtoupper(($line->description ?? '') . ' ' . ($line->reference ?? '') . ' ' . ($line->counterparty ?? ''));
        $candidates = []; // ['client_id' => ['score', 'reasons']]

        // 1. Client code in haystack
        if (preg_match_all('/ZR-C-\d{3,7}/', $haystack, $m)) {
            foreach (array_unique($m[0]) as $code) {
                $c = Client::where('code', $code)->first();
                if ($c) {
                    $this->bump($candidates, $c->id, 80, "Client code {$code} in description");
                }
            }
        }

        // 2. Booking code in haystack
        if (preg_match_all('/ZR-B-\d{3,7}/', $haystack, $m)) {
            foreach (array_unique($m[0]) as $code) {
                $b = Booking::where('code', $code)->first();
                if ($b) {
                    $this->bump($candidates, $b->client_id, 80, "Booking code {$code} in description");
                }
            }
        }

        // 3. Exact amount match against open schedule items due ±7 days from txn_date
        $start = $line->txn_date->copy()->subDays(7);
        $end = $line->txn_date->copy()->addDays(7);
        $schedules = Schedule::with('booking:id,client_id,code')
            ->whereBetween('due_date', [$start, $end])
            ->whereIn('status', ['due', 'partially_paid'])
            ->get()
            ->filter(fn ($s) => ($s->amount_minor - $s->paid_minor) === (int) $line->amount_minor);

        foreach ($schedules as $s) {
            $this->bump(
                $candidates,
                $s->booking->client_id,
                50,
                "Open schedule item with exact amount due " . $s->due_date->format('Y-m-d')
            );
        }

        // 4. Counterparty fuzzy match against client full_name
        if ($line->counterparty) {
            $clean = preg_replace('/[^A-Z ]/', '', strtoupper(trim($line->counterparty)));
            if (strlen($clean) >= 4) {
                $clients = Client::whereRaw('UPPER(full_name) LIKE ?', ['%' . $clean . '%'])->limit(20)->get();
                foreach ($clients as $c) {
                    $this->bump($candidates, $c->id, 30, "Counterparty name overlap: {$c->full_name}");
                }
            }
        }

        // 5. Phone digits in description
        $digits = preg_replace('/\D/', '', $haystack);
        if (strlen($digits) >= 10) {
            // Try last 10 digits of any phone
            foreach (Client::query()->pluck('primary_phone', 'id') as $cid => $phone) {
                $clientDigits = preg_replace('/\D/', '', (string) $phone);
                if ($clientDigits && strlen($clientDigits) >= 10 && str_contains($digits, substr($clientDigits, -10))) {
                    $this->bump($candidates, $cid, 30, "Phone digits match");
                }
            }
        }

        // Build the result, sorted by score desc
        $result = [];
        foreach ($candidates as $clientId => $info) {
            $client = Client::find($clientId);
            if (! $client) continue;
            $result[] = [
                'client_id' => $clientId,
                'client_code' => $client->code,
                'client_name' => $client->full_name,
                'score' => $info['score'],
                'reasons' => array_values(array_unique($info['reasons'])),
            ];
        }

        usort($result, fn ($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($result, 0, $limit);
    }

    private function bump(array &$candidates, int $clientId, int $points, string $reason): void
    {
        $candidates[$clientId] ??= ['score' => 0, 'reasons' => []];
        $candidates[$clientId]['score'] += $points;
        $candidates[$clientId]['reasons'][] = $reason;
    }

    /** Re-compute suggestions for every pending line in an import. */
    public function rescoreImport(int $importId): int
    {
        $count = 0;
        $lines = BankStatementLine::where('bank_statement_import_id', $importId)
            ->where('status', 'pending')
            ->cursor();

        foreach ($lines as $line) {
            $suggestions = $this->suggestFor($line);
            $line->update([
                'suggested_matches' => $suggestions,
                'matched_client_id' => $suggestions[0]['client_id'] ?? null,
                'status' => count($suggestions) ? 'matched' : 'pending',
            ]);
            $count++;
        }
        return $count;
    }
}
