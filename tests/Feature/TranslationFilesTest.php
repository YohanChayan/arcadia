<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use SplFileInfo;
use Tests\TestCase;

/**
 * Copy is one file per surface, and the editor's is a directory of them: `lang/en/editor/guides.php`
 * answers `editor.guides.*` in PHP, the key `$t()` uses for it, through `DirectoryFileLoader`.
 */
class TranslationFilesTest extends TestCase
{
    public function test_a_file_in_a_directory_is_a_key_of_the_directory_group(): void
    {
        $this->assertSame('Guides', __('editor.guides.title', [], 'en'));
        $this->assertSame('Guías', __('editor.guides.title', [], 'es'));
        $this->assertSame('Cancelar', __('editor.common.cancel', [], 'es'));
    }

    public function test_a_plain_file_still_wins_over_the_directory_loader(): void
    {
        $this->assertSame('These credentials do not match our records.', __('auth.failed', [], 'en'));
    }

    public function test_both_languages_carry_the_same_keys(): void
    {
        $this->assertSame($this->keys('en'), $this->keys('es'));
    }

    /**
     * A key spelled wrong, or left behind by a move, renders as the key itself and fails nothing
     * else. Only literal keys are checked: one built at runtime (`presentation.${state}.title`) is
     * the screen's own test's to cover.
     */
    public function test_every_literal_key_the_code_asks_for_exists_in_both_languages(): void
    {
        $asked = $this->literalKeysInCode();

        $this->assertGreaterThan(500, count($asked), 'The scan found too few keys: the pattern no longer matches the code.');

        foreach (['en', 'es'] as $locale) {
            $missing = array_values(array_filter($asked, fn (string $key): bool => ! Lang::has($key, $locale, false)));

            $this->assertSame([], $missing, "Keys asked for in the code but missing from lang/{$locale}.");
        }
    }

    /**
     * Every `__('group.key')`, `trans()`, `trans_choice()`, `$t()`, `$tChoice()` and `wTrans()`
     * whose first argument is a quoted literal.
     *
     * @return list<string>
     */
    private function literalKeysInCode(): array
    {
        $files = collect(['app', 'resources/js', 'resources/views', 'routes'])
            ->flatMap(fn (string $directory) => File::allFiles(base_path($directory)))
            ->push(new SplFileInfo(base_path('bootstrap/app.php')))
            ->filter(fn (SplFileInfo $file): bool => in_array($file->getExtension(), ['php', 'vue', 'ts', 'js']));

        $keys = $files->flatMap(function (SplFileInfo $file): array {
            preg_match_all(
                '/(?<![\w$])(?:__|trans|trans_choice|\$t|\$tChoice|wTrans|wTransChoice)\(\s*([\'"`])([a-z_]+(?:\.[a-z0-9_]+)+)\1/',
                (string) file_get_contents($file->getPathname()),
                $matches,
            );

            return $matches[2];
        });

        return $keys->unique()->sort()->values()->all();
    }

    /**
     * Every key under `lang/{locale}`, spelled as the app asks for it, except the framework's own
     * validation messages, which each language words in its own way.
     *
     * @return list<string>
     */
    private function keys(string $locale): array
    {
        $keys = [];

        foreach (File::allFiles(lang_path($locale)) as $file) {
            $group = str_replace(DIRECTORY_SEPARATOR, '.', substr($file->getRelativePathname(), 0, -4));

            if ($group === 'validation') {
                continue;
            }

            foreach (array_keys(Arr::dot(require $file->getPathname())) as $key) {
                $keys[] = "{$group}.{$key}";
            }
        }

        sort($keys);

        return $keys;
    }
}
