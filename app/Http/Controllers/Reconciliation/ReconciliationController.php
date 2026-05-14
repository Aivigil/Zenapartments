<?php

namespace App\Http\Controllers\Reconciliation;

use App\Http\Controllers\Controller;
use App\Models\BankStatementImport;
use App\Models\BankStatementLine;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\AuditEventWriter;
use App\Services\PaymentAllocator;
use App\Services\Reconciliation\CsvImporter;
use App\Services\Reconciliation\SuggestedMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReconciliationController extends Controller
{
    public function __construct(
        private CsvImporter $importer,
        private SuggestedMatcher $matcher,
        private PaymentAllocator $allocator,
    ) {
    }

    public function index(Request $request): Response
    {
        $request->user()->can('reconciliation.view') || abort(403);

        $imports = BankStatementImport::query()
            ->withCount([
                'lines',
                'lines as confirmed_count' => fn ($q) => $q->where('status', 'confirmed'),
                'lines as pending_count' => fn ($q) => $q->whereIn('status', ['pending', 'matched']),
                'lines as ignored_count' => fn ($q) => $q->where('status', 'ignored'),
            ])
            ->orderByDesc('created_at')
            ->paginate(25)
            ->through(fn ($i) => [
                'id' => $i->id,
                'bank_account' => $i->bank_account,
                'period_start' => $i->period_start?->format('Y-m-d'),
                'period_end' => $i->period_end?->format('Y-m-d'),
                'source_filename' => $i->source_filename,
                'total_lines' => $i->total_lines,
                'lines_count' => $i->lines_count,
                'confirmed_count' => $i->confirmed_count,
                'pending_count' => $i->pending_count,
                'ignored_count' => $i->ignored_count,
                'created_at' => $i->created_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Reconciliation/Index', [
            'imports' => $imports,
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->user()->can('reconciliation.match') || abort(403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'bank_account' => ['required', 'string', 'max:255'],
            'col_date' => ['nullable', 'string'],
            'col_description' => ['nullable', 'string'],
            'col_credit' => ['nullable', 'string'],
            'col_debit' => ['nullable', 'string'],
            'col_amount' => ['nullable', 'string'],
            'col_reference' => ['nullable', 'string'],
            'col_counterparty' => ['nullable', 'string'],
        ]);

        $colMap = array_filter([
            'date' => $request->input('col_date'),
            'description' => $request->input('col_description'),
            'credit' => $request->input('col_credit'),
            'debit' => $request->input('col_debit'),
            'amount' => $request->input('col_amount'),
            'reference' => $request->input('col_reference'),
            'counterparty' => $request->input('col_counterparty'),
        ]);

        try {
            $import = $this->importer->import(
                $request->file('file'),
                $request->string('bank_account'),
                $colMap
            );
            // Suggest matches for every pending line
            $matched = $this->matcher->rescoreImport($import->id);
            $import->update(['matched_lines' => $matched]);

            return redirect()
                ->route('reconciliation.show', $import)
                ->with('success', "Imported {$import->total_lines} lines; {$matched} have suggested matches.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    public function show(Request $request, BankStatementImport $import): Response
    {
        $request->user()->can('reconciliation.view') || abort(403);

        $statusFilter = $request->input('status', 'pending_or_matched');

        $linesQuery = BankStatementLine::where('bank_statement_import_id', $import->id);

        $linesQuery = match ($statusFilter) {
            'all' => $linesQuery,
            'confirmed' => $linesQuery->where('status', 'confirmed'),
            'ignored' => $linesQuery->where('status', 'ignored'),
            default => $linesQuery->whereIn('status', ['pending', 'matched']),
        };

        $lines = $linesQuery
            ->orderBy('txn_date')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn ($l) => [
                'id' => $l->id,
                'txn_date' => $l->txn_date?->format('Y-m-d'),
                'direction' => $l->direction,
                'amount_minor' => $l->amount_minor,
                'currency' => $l->currency,
                'description' => $l->description,
                'counterparty' => $l->counterparty,
                'reference' => $l->reference,
                'status' => $l->status,
                'suggested_matches' => $l->suggested_matches ?? [],
                'matched_client_id' => $l->matched_client_id,
            ]);

        return Inertia::render('Reconciliation/Show', [
            'import' => [
                'id' => $import->id,
                'bank_account' => $import->bank_account,
                'period_start' => $import->period_start?->format('Y-m-d'),
                'period_end' => $import->period_end?->format('Y-m-d'),
                'source_filename' => $import->source_filename,
                'total_lines' => $import->total_lines,
                'matched_lines' => $import->matched_lines,
            ],
            'lines' => $lines,
            'filter' => $statusFilter,
        ]);
    }

    /**
     * Confirm a line: pick a booking, create a Payment from it, FIFO-allocate.
     * Body: { booking_id }.
     */
    public function confirm(Request $request, BankStatementLine $line): RedirectResponse
    {
        $request->user()->can('reconciliation.confirm') || abort(403);

        $request->validate([
            'booking_id' => ['required', 'exists:bookings,id'],
        ]);

        if ($line->status === 'confirmed') {
            return back()->with('error', 'Line already confirmed.');
        }
        if ($line->direction !== 'credit') {
            return back()->with('error', 'Only credit lines can be confirmed as payments.');
        }

        $booking = Booking::findOrFail($request->integer('booking_id'));

        $payment = DB::transaction(function () use ($line, $booking, $request) {
            $payment = Payment::create([
                'client_id' => $booking->client_id,
                'booking_id' => $booking->id,
                'channel' => 'bank_transfer',
                'amount_minor' => $line->amount_minor,
                'currency' => $line->currency,
                'pkr_amount_minor' => $line->amount_minor,
                'received_at' => $line->txn_date,
                'bank_account' => $line->bank_account,
                'bank_reference' => $line->reference,
                'bank_statement_line_id' => $line->id,
                'posted_by' => $request->user()->id,
                'status' => 'posted',
                'notes' => "Reconciled from bank statement (line #{$line->id}).",
            ]);
            $payment->code = generate_code('ZR-P-', $payment->id, 6);
            $payment->save();

            $this->allocator->allocate($payment);

            $line->update([
                'status' => 'confirmed',
                'matched_client_id' => $booking->client_id,
                'matched_payment_id' => $payment->id,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            return $payment;
        });

        AuditEventWriter::record(
            event: 'reconciliation.confirmed',
            subject: $payment,
            after: [
                'bank_statement_line_id' => $line->id,
                'booking_id' => $booking->id,
                'amount_minor' => $payment->amount_minor,
            ],
        );

        return back()->with('success', "Line confirmed — payment {$payment->code} posted and allocated.");
    }

    /** Mark a line as ignored (won't show in pending queue). */
    public function ignore(Request $request, BankStatementLine $line): RedirectResponse
    {
        $request->user()->can('reconciliation.match') || abort(403);

        $line->update([
            'status' => 'ignored',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Line marked as ignored.');
    }

    /** AJAX endpoint — return active bookings for a list of client IDs. */
    public function suggestedBookings(Request $request)
    {
        $request->user()->can('reconciliation.view') || abort(403);
        $ids = collect(explode(',', (string) $request->input('client_ids')))
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->all();

        if (empty($ids)) return response()->json([]);

        $bookings = Booking::with('client:id,full_name')
            ->whereIn('client_id', $ids)
            ->where('status', 'active')
            ->get(['id', 'code', 'client_id'])
            ->map(fn ($b) => [
                'id' => $b->id,
                'code' => $b->code,
                'client_name' => $b->client?->full_name,
            ]);

        return response()->json($bookings);
    }
}
