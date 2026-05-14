<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use HasFactory;

    protected $table = 'notification_log';

    protected $fillable = [
        'client_id', 'booking_id', 'schedule_id', 'notification_template_id',
        'template_code', 'channel', 'recipient', 'subject', 'body',
        'status', 'provider', 'provider_message_id', 'provider_response',
        'queued_at', 'sent_at', 'delivered_at', 'failed_at', 'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'provider_response' => 'array',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }
}
