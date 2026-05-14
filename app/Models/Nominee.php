<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Nominee extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'full_name', 'relationship',
        'cnic_encrypted', 'cnic_hash', 'phone', 'address',
    ];

    protected $hidden = ['cnic_encrypted', 'cnic_hash'];

    protected function casts(): array
    {
        return [
            'cnic_encrypted' => 'encrypted',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
