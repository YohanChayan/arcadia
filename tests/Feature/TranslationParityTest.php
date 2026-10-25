<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Tests\TestCase;

/**
 * Every lang file says the same things in both languages: a key in one and not the other renders
 * as its raw path in the other — `$t()` gives the key back rather than failing.
 */
class TranslationParityTest extends TestCase
{
    public function test_spanish_and_english_carry_the_same_keys(): void
    {
        $files = fn (string $locale) => collect(glob(lang_path("{$locale}/*.php")))->map(fn ($path) => basename($path))->sort()->values()->all();

        $this->assertSame($files('es'), $files('en'), 'The two locales hold different files.');

        foreach ($files('es') as $file) {
            $keys = fn (string $locale) => array_keys(Arr::dot(require lang_path("{$locale}/{$file}")));

            $this->assertSame(
                [],
                array_values(array_diff($keys('es'), $keys('en'))) + array_values(array_diff($keys('en'), $keys('es'))),
                "{$file} differs between es and en.",
            );
        }
    }
}
