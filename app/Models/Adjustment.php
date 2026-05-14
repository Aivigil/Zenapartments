<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Adjustment extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'code', 'booking_id', 'schedule_id', 'kind', 'direction',
        'amount_minor', 'currency', 'effective_on', 'reason',
        'requested_by', 'approved_by', 'approved_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'effective_on' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
