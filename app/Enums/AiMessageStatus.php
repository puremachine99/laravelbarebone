<?php

namespace App\Enums;

enum AiMessageStatus: string
{
    case Complete = 'complete';
    case Streaming = 'streaming';
    case Error = 'error';
    case Cancelled = 'cancelled';
}
