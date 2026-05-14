<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'channel', 'subject', 'body', 'variables', 'active', 'version',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'active' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }
}
