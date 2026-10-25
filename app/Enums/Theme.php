<?php

namespace App\Enums;

/**
 * The two themes an account can choose. There is no third "system" value — ADR 0002 rejected
 * it — so an account that has not chosen holds `NULL`, not a case of this enum.
 */
enum Theme: string
{
    case LIGHT = 'light';
    case DARK = 'dark';
}
