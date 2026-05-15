# migration-samples/

Drop **anonymised** sample files from OneDrive here. I'll use them to build
importers + the opening-balance verification report tailored to your exact
column names, date formats, and conventions.

## What to drop

1. **`master-client-list.xlsx`** (or `.xls` / `.csv`)
   - The roster of all clients.
   - Anonymise: replace real names with "Client A", "Client B" etc.,
     phones with "+92 300 0000001" pattern, CNICs with "12345-1234567-1"
     style fakes. Keep the **column layout, headers, and formats exactly
     as the original**.

2. **`client-ledger-sample-1.xlsx`** and **`client-ledger-sample-2.xlsx`**
   - Two examples of per-client account ledgers — pick ones that look
     representative (one early-stage, one mid-installments).
   - Anonymise the same way.
   - Keep the layout intact: where the down payment row sits, where
     installments are listed, where "received" amounts get recorded,
     where the running balance lives.

3. **`bank-statement-sample.csv`** (or `.xlsx`)
   - One month of a bank export with at least 20 line items.
   - Real bank reference numbers can stay (they're not PII the way names are).
   - If client codes/names appear in the description column, anonymise
     those the same way you did in step 1 so they match.

## What I'll do with these

Once they're in this folder I'll build:

- `php artisan migrate:clients --file=...` — pulls the master list,
  creates Client rows, dedup on CNIC HMAC.
- `php artisan migrate:bookings --file=...` — creates Booking + Schedule,
  back-dates the schedule, ages historical installments correctly.
- `php artisan migrate:ledger --file=...` — reads a per-client ledger,
  creates Payment + Allocation rows matching the historical receipts.
- `php artisan reconcile:opening-balances` — produces the variance report:
  every client's expected outstanding vs portal-computed, flagged when ≠ 0.

All run with `--dry-run` first so finance can review before any DB write.
