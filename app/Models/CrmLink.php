<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CrmLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'crm_provider', 'local_type', 'local_id',
        'remote_entity_type', 'remote_entity_id',
        'last_synced_at', 'last_payload_in', 'last_payload_out', 'sync_status',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
            'last_payload_in' => 'array',
            'last_payload_out' => 'array',
        ];
    }

    public function local(): MorphTo
    {
        return $this->morphTo();
    }
}
