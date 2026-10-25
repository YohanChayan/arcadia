<?php

namespace App\Enums;

/**
 * What a version changed. The owner's record, and what decides whether a buyer of an earlier
 * version is offered the new one: a new exam is worth offering, a fixed link was already given
 * to everyone by fix-everywhere.
 */
enum ChangeKind: string
{
    case NEW_VIDEO = 'new_video';
    case NEW_PRACTICE = 'new_practice';
    case NEW_EXAM = 'new_exam';
    case MODIFIED = 'modified';
    case REMOVED = 'removed';
    case FIXED = 'fixed';
    case OTHER = 'other';
}
