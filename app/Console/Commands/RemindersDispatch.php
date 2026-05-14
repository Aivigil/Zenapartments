<?php

namespace App\Console\Commands;

use App\Services\Notifications\ReminderDispatcher;
use Illuminate\Console\Command;

class RemindersDispatch extends Command
{
    protected $signature = 'reminders:dispatch';

    protected $description = 'Queue + send payment reminders for upcoming and overdue schedule items.';

    public function handle(ReminderDispatcher $dispatcher): int
    {
        $this->info('Dispatching reminders…');
        $stats = $dispatcher->run();
        $this->table(
            ['kind', 'count'],
            collect($stats)->map(fn ($v, $k) => ['kind' => $k, 'count' => $v])->values()->toArray()
        );
        return self::SUCCESS;
    }
}
