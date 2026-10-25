<?php

namespace App\Enums;

/**
 * A guide is a draft until the owner publishes it. An imported AI result always arrives as one,
 * so nothing reaches a reader unreviewed.
 */
enum GuideStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
}
