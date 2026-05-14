<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;

class Payment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'code', 'client_id', 'booking_id',
        'channel', 'amount_minor', 'currency', 'fx_rate', 'pkr_amount_minor',
        'received_at', 'bank_account', 'bank_reference', 'bank_statement_line_id',
        'status', 'reversed_on', 'reversal_reason', 'reversed_by', 'posted_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'channel' => PaymentChannel::class,
            'status' => PaymentStatus::class,
            'amount_minor' => 'integer',
            'pkr_amount_minor' => 'integer',
            'fx_rate' => 'decimal:8',
            'received_at' => 'date',
            'reversed_on' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function unallocatedMinor(): int
    {
        $base = $this->pkr_amount_minor ?? $this->amount_minor;
        return (int) ($base - $this->allocations()->sum('amount_minor'));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
