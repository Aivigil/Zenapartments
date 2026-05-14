<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_statement_import_id', 'bank_account', 'txn_date', 'direction',
        'amount_minor', 'currency', 'description', 'counterparty', 'reference',
        'raw', 'status', 'suggested_matches', 'matched_client_id', 'matched_payment_id',
        'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'txn_date' => 'date',
            'amount_minor' => 'integer',
            'raw' => 'array',
            'suggested_matches' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(BankStatementImport::class, 'bank_statement_import_id');
    }

    public function matchedClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'matched_client_id');
    }

    public function matchedPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'matched_payment_id');
    }
}
