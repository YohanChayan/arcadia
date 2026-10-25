<?php

namespace App\Enums;

/**
 * What a visitor wants a guide for — the popup's one-tap "¿Para qué?". Optional: a visitor who
 * skips it still asked for a guide.
 */
enum InquiryPurpose: string
{
    case JOB = 'job';
    case IMPROVE = 'improve';
    case OWN = 'own';
    case FUN = 'fun';
}
