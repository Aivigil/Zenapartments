<?php

namespace App\Services\Notifications\Channels;

use App\Models\NotificationLog;

interface Channel
{
    public function send(NotificationLog $log): void;

    public function name(): string;
}
