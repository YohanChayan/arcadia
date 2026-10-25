<?php

namespace App\Enums;

/**
 * Where an enrollment request stands (ADR 0022). Pending until the owner answers; approved once
 * the account is a reader — whether he approved it or added the email himself; declined for good,
 * which the guide's link then reads as "no access".
 */
enum AccessRequestStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case DECLINED = 'declined';
}
