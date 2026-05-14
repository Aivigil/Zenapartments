<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Booking extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'code', 'client_id', 'unit_id', 'plan_template_id',
        'booking_date', 'total_price_minor', 'currency', 'down_payment_minor',
        'status', 'cancelled_on', 'cancellation_reason', 'cancelled_by',
        'salesperson_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'cancelled_on' => 'date',
            'total_price_minor' => 'integer',
            'down_payment_minor' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function planTemplate(): BelongsTo
    {
        return $this->belongsTo(PlanTemplate::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class)->orderBy('sequence_no');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(Adjustment::class);
    }

    public function coBuyers(): HasMany
    {
        return $this->hasMany(BookingCoBuyer::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'owner');
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /**
     * Compute current outstanding balance in minor units.
     * Honours the ledger invariant from Discovery doc §7.3.
     */
    public function outstandingMinor(): int
    {
        $scheduled = $this->schedules()
            ->whereNotIn('status', ['waived', 'written_off', 'cancelled'])
            ->sum('amount_minor');

        $allocated = \DB::table('allocations')
            ->join('payments', 'payments.id', '=', 'allocations.payment_id')
            ->join('schedules', 'schedules.id', '=', 'allocations.schedule_id')
            ->where('schedules.booking_id', $this->id)
            ->where('payments.status', 'posted')
            ->sum('allocations.amount_minor');

        $creditAdjustments = $this->adjustments()
            ->where('status', 'approved')
            ->where('direction', 'credit')
            ->sum('amount_minor');

        $debitAdjustments = $this->adjustments()
            ->where('status', 'approved')
            ->where('direction', 'debit')
            ->sum('amount_minor');

        return (int) ($scheduled - $allocated - $creditAdjustments + $debitAdjustments);
    }
}
