<?php

namespace App\Enums;

/**
 * What one checkable line of a guide is. The first three are links to someone else's content —
 * the guide links and embeds, never re-hosts. The last two are the guide's own words.
 */
enum ItemKind: string
{
    case YOUTUBE_VIDEO = 'youtube_video';
    case YOUTUBE_PLAYLIST = 'youtube_playlist';
    case EXTERNAL_SITE = 'external_site';
    case NOTE = 'note';
    case PRACTICE = 'practice';

    public function isLink(): bool
    {
        return in_array($this, [self::YOUTUBE_VIDEO, self::YOUTUBE_PLAYLIST, self::EXTERNAL_SITE], true);
    }
}
