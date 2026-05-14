<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PlanTemplate extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'description',
        'down_payment_bps', 'installment_count', 'installment_frequency',
        'milestone_charges', 'late_fee_policy', 'early_payment_discount',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'down_payment_bps' => 'integer',
            'installment_count' => 'integer',
            'milestone_charges' => 'array',
            'late_fee_policy' => 'array',
            'early_payment_discount' => 'array',
            'active' => 'boolean',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
