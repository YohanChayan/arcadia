<?php

namespace App\Enums;

/**
 * What the guides list does to every guide selected at once.
 *
 * Publishing is `PublishGuide`'s, so it releases a pending draft and leaves out a guide with
 * nothing to release; taking down only sets the status; deleting takes versions, content and
 * images with each guide, as deleting one does.
 */
enum GuideBulkAction: string
{
    case PUBLISH = 'publish';
    case DRAFT = 'draft';
    case DELETE = 'delete';
}
