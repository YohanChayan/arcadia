<?php

namespace App\Assistant\Agents;

use Laravel\Ai\Attributes\CacheInstructions;

/**
 * The speaker with its instructions marked as a cache breakpoint, used when
 * `assistant.cache_instructions` is on. `laravel/ai` reads the mark from an attribute only, so
 * switching it is a choice of class. Honoured by providers with explicit caching (Anthropic);
 * Gemini ignores it and caches the stable prefix on its own.
 */
#[CacheInstructions]
class CachedSpeaker extends Speaker {}
