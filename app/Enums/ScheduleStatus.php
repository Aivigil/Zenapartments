<?php

namespace App\Enums;

enum ScheduleStatus: string
{
    case Due = 'due';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Waived = 'waived';
    case WrittenOff = 'written_off';
    case Cancelled = 'cancelled';
}
