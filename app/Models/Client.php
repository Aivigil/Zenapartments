<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Client extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'code', 'full_name', 'father_or_husband_name', 'date_of_birth',
        'nationality', 'country_of_residence',
        'cnic_encrypted', 'passport_encrypted', 'cnic_hash',
        'primary_phone', 'alt_phone', 'email',
        'address_line1', 'address_line2', 'city', 'country',
        'kyc_status', 'kyc_verified_at', 'kyc_verified_by',
        'preferences', 'notes',
    ];

    protected $hidden = [
        'cnic_encrypted', 'passport_encrypted', 'cnic_hash',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'kyc_verified_at' => 'datetime',
            'cnic_encrypted' => 'encrypted',
            'passport_encrypted' => 'encrypted',
            'preferences' => 'array',
        ];
    }

    // Convenience accessors that decrypt for authorised contexts.
    protected function cnic(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->cnic_encrypted,
            set: function ($value) {
                if (! $value) {
                    return [
                        'cnic_encrypted' => null,
                        'cnic_hash' => null,
                    ];
                }
                return [
                    'cnic_encrypted' => $value, // 'encrypted' cast will encrypt
                    'cnic_hash' => hash_hmac('sha256', preg_replace('/\D/', '', $value), config('app.key')),
                ];
            }
        );
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function nominees(): HasMany
    {
        return $this->hasMany(Nominee::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'owner');
    }

    public function user(): HasMany
    {
        // A client may have one or more login user accounts (primary buyer + co-buyers)
        return $this->hasMany(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'full_name', 'primary_phone', 'alt_phone', 'email',
                'address_line1', 'city', 'country', 'kyc_status',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
