<?php

namespace App\Translation;

use Illuminate\Translation\FileLoader;

/**
 * Laravel's file loader, which also reads a group from a directory: when `lang/{locale}/editor.php`
 * does not exist but `lang/{locale}/editor/` does, each file in it becomes a key of the group —
 * `lang/en/editor/guides.php` answers `__('editor.guides.title')`.
 *
 * That is the same key `laravel-vue-i18n` gives the file when it compiles `lang/` for `$t()`, so a
 * string has one spelling in PHP and in Vue. Laravel alone would only reach it as `editor/guides.title`.
 */
class DirectoryFileLoader extends FileLoader
{
    /**
     * The same loader, with its paths, JSON paths and namespaces, reading directories too.
     */
    public static function from(FileLoader $loader): self
    {
        $directoryLoader = new self($loader->files, $loader->paths);
        $directoryLoader->jsonPaths = $loader->jsonPaths;
        $directoryLoader->hints = $loader->hints;

        return $directoryLoader;
    }

    /**
     * @param  array<int, string>  $paths
     * @param  string  $locale
     * @param  string  $group
     * @return array<string, mixed>
     */
    protected function loadPaths(array $paths, $locale, $group): array
    {
        return collect($paths)->reduce(function (array $output, string $path) use ($locale, $group): array {
            $directory = "{$path}/{$locale}/{$group}";

            if ($this->files->exists("{$directory}.php") || ! $this->files->isDirectory($directory)) {
                return $output;
            }

            return array_replace_recursive($output, $this->loadDirectory($directory));
        }, parent::loadPaths($paths, $locale, $group));
    }

    /**
     * Every PHP file in the directory under its own name, and every subdirectory the same way.
     *
     * @return array<string, mixed>
     */
    private function loadDirectory(string $directory): array
    {
        $lines = [];

        foreach ($this->files->files($directory) as $file) {
            if ($file->getExtension() === 'php') {
                $lines[$file->getFilenameWithoutExtension()] = $this->files->getRequire($file->getPathname());
            }
        }

        foreach ($this->files->directories($directory) as $subdirectory) {
            $lines[basename($subdirectory)] = $this->loadDirectory($subdirectory);
        }

        return $lines;
    }
}
