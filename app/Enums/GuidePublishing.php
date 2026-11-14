<?php

namespace App\Enums;

/**
 * What publishing a guide would do to it — asked before the owner confirms, and answered again
 * when it is done (`PublishGuide`).
 */
enum GuidePublishing: string
{
    /** Published, with nothing unreleased waiting: nothing to do. */
    case READY = 'ready';

    /** Its draft holds content not yet released: released with it, and the guide published. */
    case RELEASES_DRAFT = 'releases_draft';

    /** Already has a released version; only its status goes to published. */
    case PUBLISHES = 'publishes';

    /** Nothing released and an empty draft: there is nothing a reader could open, so it is left alone. */
    case NO_CONTENT = 'no_content';
}
