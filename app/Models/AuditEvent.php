<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only financial audit log. Do not allow update or delete.
 */
class AuditEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'actor_id', 'actor_role', 'event',
        'subject_type', 'subject_id', 'before', 'after',
        'reason', 'ip', 'user_agent', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('AuditEvent records are immutable.');
        });

        static::deleting(function () {
            throw new \LogicException('AuditEvent records cannot be deleted.');
        });
    }
}
