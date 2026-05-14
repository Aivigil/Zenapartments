<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Enums\UnitStatus;

class Unit extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'project_id', 'block_id', 'unit_category_id',
        'code', 'name', 'size_value', 'size_unit',
        'base_price_minor', 'currency',
        'status', 'attributes', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'size_value' => 'decimal:3',
            'base_price_minor' => 'integer',
            'attributes' => 'array',
            'status' => UnitStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(UnitCategory::class, 'unit_category_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function activeBooking(): ?Booking
    {
        return $this->bookings()->where('status', 'active')->first();
    }

    public function isAvailable(): bool
    {
        return $this->status === UnitStatus::Available;
    }

    /** Major-unit accessor for UI. */
    public function getBasePriceAttribute(): float
    {
        return $this->base_price_minor / 100;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
