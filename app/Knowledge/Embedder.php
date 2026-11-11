<?php

namespace App\Knowledge;

use Laravel\Ai\Embeddings;

/**
 * Turns text into vectors with the provider, model and dimensions in `config/knowledge.php`, the
 * same for passages and for the questions asked of them — vectors from different models cannot
 * be compared.
 */
class Embedder
{
    public function model(): string
    {
        return config('knowledge.embeddings.model');
    }

    /**
     * One vector per text, in the order given.
     *
     * @param  list<string>  $texts
     * @return list<array<float>>
     */
    public function embed(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        return array_values(Embeddings::for($texts)
            ->dimensions(config('knowledge.embeddings.dimensions'))
            ->generate(config('knowledge.embeddings.provider'), $this->model())
            ->embeddings);
    }
}
