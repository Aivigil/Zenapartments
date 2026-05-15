<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ClientPortalToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'booking_id', 'token', 'expires_at',
        'last_used_at', 'last_used_ip', 'use_count',
        'created_by', 'revoked_at', 'revoked_by', 'label',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
            'use_count' => 'integer',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        if ($this->revoked_at !== null) return false;
        if ($this->expires_at !== null && $this->expires_at->isPast()) return false;
        return true;
    }

    public static function generateToken(): string
    {
        // 40-char URL-safe token, plenty of entropy for an unauthenticated read URL
        do {
            $token = Str::random(40);
        } while (static::where('token', $token)->exists());
        return $token;
    }
}
