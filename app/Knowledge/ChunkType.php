<?php

namespace App\Knowledge;

/**
 * What a chunk in the knowledge index describes. Each is promise-level: what a guide sets out to
 * teach, never what is inside it — no lessons, item briefs, links or creators (ADR 0022).
 */
enum ChunkType: string
{
    /** The guide's title, level, goals, pitch and the titles of its stages. */
    case GUIDE = 'guide';

    /** One stage's title and goal, under its guide's title. */
    case STAGE = 'stage';
}
