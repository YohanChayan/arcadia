<?php

namespace App\Enums;

/**
 * What listing a guide in the catalogue would do to it — asked before the owner confirms, and
 * answered again when it is done (`ListGuide`).
 */
enum GuideListing: string
{
    /** Already open and in the catalogue, and readable: it is listed now; nothing to do. */
    case READY = 'ready';

    /** Readable: opened for enrollment, kept in the catalogue, and listed at once. */
    case LISTS = 'lists';

    /** Not readable yet — a draft, or nothing released: marked for the catalogue, listed once published. */
    case AWAITS_PUBLISHING = 'awaits_publishing';
}
