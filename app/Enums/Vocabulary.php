<?php

namespace App\Enums;

use App\Models\Category;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Model;

/**
 * The lists of terms guides are grouped by — one per locale, written by the owner, each term a
 * name and the slug it becomes. One enum so one editor screen and one controller serve both.
 *
 * Goals were a third until 2026-10-16: each was one guide's own outcome, so they are now lines of
 * text on the guide (ADR 0020). What several guides share is a category or a topic.
 */
enum Vocabulary: string
{
    case CATEGORIES = 'categories';
    case TOPICS = 'topics';

    /** @return class-string<Model> */
    public function model(): string
    {
        return match ($this) {
            self::CATEGORIES => Category::class,
            self::TOPICS => Topic::class,
        };
    }

    public function table(): string
    {
        return (new ($this->model()))->getTable();
    }
}
