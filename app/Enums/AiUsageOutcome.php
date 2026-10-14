<?php

namespace App\Enums;

/**
 * How one AI request ended, as recorded in the usage log.
 */
enum AiUsageOutcome: string
{
    case Ok = 'ok';
    case Error = 'error';
    case Denied = 'denied';
    case Cancelled = 'cancelled';
}
