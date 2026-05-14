<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Schedule extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'booking_id', 'sequence_no', 'due_date',
        'amount_minor', 'paid_minor', 'currency',
        'category', 'label', 'status', 'paid_on', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_on' => 'date',
            'amount_minor' => 'integer',
            'paid_minor' => 'integer',
            'sequence_no' => 'integer',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    public function getOutstandingMinorAttribute(): int
    {
        return max(0, $this->amount_minor - $this->paid_minor);
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'paid'
            && $this->status !== 'waived'
            && $this->due_date?->isPast() === true;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'paid_minor', 'paid_on', 'amount_minor', 'due_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
