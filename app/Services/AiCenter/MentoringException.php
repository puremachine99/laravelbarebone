<?php

namespace App\Services\AiCenter;

use RuntimeException;

/**
 * The pendampingan service refused or failed a request. The message is the user-facing one; it never carries the API key.
 */
class MentoringException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}
