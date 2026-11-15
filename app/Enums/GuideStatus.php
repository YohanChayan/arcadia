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

    /** @return list<array{value: string, label: string, tone: string}> every status, with its badge tone */
    public static function options(): array
    {
        return array_map(fn (self $status) => [
            'value' => $status->value,
            'label' => __("arcadia.editor.guides.statuses.{$status->value}"),
            'tone' => $status === self::PUBLISHED ? 'ok' : 'neutral',
        ], self::cases());
    }
}
