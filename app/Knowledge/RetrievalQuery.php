<?php

namespace App\Knowledge;

/**
 * What a caller asks the knowledge index: the passages closest to `text`, in one locale. By
 * default only guides a reader can enrol in are searched, since nothing else can be offered.
 */
final readonly class RetrievalQuery
{
    public function __construct(
        public string $text,
        public string $locale,
        public int $limit = 5,
        public bool $listedOnly = true,
    ) {}
}
