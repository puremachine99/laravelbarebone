<?php

namespace App\Enums;

/**
 * Which kind of AI call a usage log row is for: the user's chat answer, or the hidden data-planning call before it.
 */
enum AiUsageKind: string
{
    case Chat = 'chat';
    case DataPlan = 'data_plan';
}
