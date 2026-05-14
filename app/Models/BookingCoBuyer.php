<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingCoBuyer extends Model
{
    use HasFactory;

    protected $fillable = ['booking_id', 'client_id', 'share_bps', 'is_primary'];

    protected function casts(): array
    {
        return [
            'share_bps' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
