<?php

namespace App\Enums;

/**
 * Where a request stands, read from the work done on it — never set by hand: nothing yet, or a
 * guide started to answer it. Replaced the read/unread marker on 2026-10-21, which said whether a
 * row had been seen, not whether it had been answered. A middle stage, a prompt copied, went on
 * 2026-10-22 with the prompt itself, which is now prepared on the guide.
 */
enum InquiryStage: string
{
    case PENDING = 'pending';
    case GUIDE = 'guide';

    /** The badge tone the editor shows it in. */
    public function tone(): string
    {
        return match ($this) {
            self::PENDING => 'pending',
            self::GUIDE => 'ok',
        };
    }
}
