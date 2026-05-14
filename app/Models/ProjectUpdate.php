<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectUpdate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id', 'title', 'body', 'category',
        'broadcast', 'broadcasted', 'broadcasted_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'broadcast' => 'boolean',
            'broadcasted' => 'boolean',
            'broadcasted_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Document::class, 'owner');
    }
}
