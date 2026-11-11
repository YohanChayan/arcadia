<?php

/*
|--------------------------------------------------------------------------
| The sales assistant
|--------------------------------------------------------------------------
|
| The chat that finds a visitor the guide that fits and moves them towards it. Its provider and
| its credentials are `config/ai.php`'s; what is here is the assistant's own: which model plans
| a turn and which one speaks, how much history either is shown, and what a token costs.
|
*/

return [

    // The provider both calls go to. Caching on Gemini is implicit — see `cache_instructions`.
    'provider' => env('ASSISTANT_PROVIDER', 'gemini'),

    // Two calls per visitor turn: the planner reads the visitor (engagement, an acceptance, the
    // guide to offer) as structured output, then the speaker streams the reply. Streaming and
    // structured output cannot be combined in `laravel/ai`.
    'models' => [
        'planner' => env('ASSISTANT_PLANNER_MODEL', 'gemini-3.1-flash-lite'),
        'speaker' => env('ASSISTANT_SPEAKER_MODEL', 'gemini-3.1-flash-lite'),
    ],

    // The languages the assistant speaks: one set of prompts each, in `resources/views/assistant`.
    'locales' => ['es'],

    // How many earlier turns the model is shown, and how many passages each turn retrieves.
    'history_turns' => 20,
    'passages' => 5,

    // Marks the static instructions as a cache breakpoint for providers with explicit caching
    // (Anthropic). Gemini caches a stable prefix implicitly and ignores this flag; the static
    // instructions are kept byte-stable for it either way.
    'cache_instructions' => (bool) env('ASSISTANT_CACHE_INSTRUCTIONS', true),

    // USD per million tokens, by model. A model with no entry is shown as having no cost.
    // 'gemini-3.1-flash-lite' => ['input' => 0.0, 'cached_input' => 0.0, 'output' => 0.0],
    'prices' => [],

];
