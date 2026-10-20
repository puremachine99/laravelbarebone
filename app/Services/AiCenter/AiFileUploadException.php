<?php

namespace App\Services\AiCenter;

use App\Enums\AiErrorCode;
use RuntimeException;

/**
 * The AI service refused or failed an upload. The message is the user-facing one; it never carries the API key.
 */
class AiFileUploadException extends RuntimeException
{
    public function __construct(public readonly AiErrorCode $errorCode)
    {
        parent::__construct($errorCode->message());
    }
}
