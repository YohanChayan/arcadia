<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A prompt as the fill-in form shows it: the text, the required blanks still empty, and any
 * placeholder no variable declares.
 *
 * @property array{prompt: string, missing: list<string>, undeclared: list<string>} $resource
 */
class FilledPromptResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
