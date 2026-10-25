<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What an import would do, before it does it: the problems found, each at its place, or a
 * summary of what would be written — and whether the draft already has content to replace.
 *
 * @property array{problems: list<array{place: string, message: string}>, summary: array<string, mixed>|null, has_content: bool} $resource
 */
class ImportPreviewResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
