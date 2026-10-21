<?php

namespace App\Enums;

/**
 * Light or dark variant of the user's UserTheme; "system" follows the device setting.
 */
enum UserAppearance: string
{
    case Light = 'light';
    case Dark = 'dark';
    case System = 'system';
}
