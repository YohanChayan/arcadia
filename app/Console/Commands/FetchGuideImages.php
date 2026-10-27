<?php

namespace App\Console\Commands;

use App\Models\GuideImage;
use App\Services\ImageCrops;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Brings the files behind imported `guide_images` rows from another Arcadia, by their public URL:
 * the original and every crop its slot cuts, each to the path its row names, so nothing in the
 * database changes. A file already on the disk is left alone, so it can run again and fetch only
 * what is new. A crop the other host does not have is cut again from the original, as an upload
 * would cut it.
 */
#[Signature('arcadia:fetch-images {from : The Arcadia to copy from, e.g. https://arcadia.yhndevside.com}')]
#[Description("Download the files of the guide images in this database from another Arcadia's public URLs")]
class FetchGuideImages extends Command
{
    /** @var array{present: int, downloaded: int, recut: int, missing: int} */
    private array $counts = ['present' => 0, 'downloaded' => 0, 'recut' => 0, 'missing' => 0];

    public function handle(ImageCrops $crops): int
    {
        $from = rtrim((string) $this->argument('from'), '/');
        $disk = Storage::disk(GuideImage::DISK);

        foreach (GuideImage::query()->lazyById() as $image) {
            $original = $this->fetch($disk, $from, $image->path());

            if ($original === null) {
                $this->missing($image->path());
            }

            foreach ($image->slot->crops() as $name => $size) {
                $path = $image->path($name);

                if ($this->fetch($disk, $from, $path) !== null) {
                    continue;
                }

                if ($original === null) {
                    $this->missing($path);

                    continue;
                }

                $disk->put($path, $crops->cut($original, [$name => $size])[$name]);
                $this->counts['recut']++;
                $this->line("  cut again  {$path}");
            }
        }

        $this->table(['Already here', 'Downloaded', 'Cut again', 'Missing'], [array_values($this->counts)]);

        return $this->counts['missing'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * The file's contents, from the disk or else from the other host, saved on the way; null when
     * neither has it.
     */
    private function fetch(Filesystem $disk, string $from, string $path): ?string
    {
        if ($disk->exists($path)) {
            $this->counts['present']++;

            return $disk->get($path);
        }

        try {
            // A 404 is an answer; only a dropped connection or a 5xx is worth asking again.
            $response = Http::timeout(30)
                ->retry(2, 500, when: fn (Throwable $e) => ! $e instanceof RequestException || $e->response->serverError(), throw: false)
                ->get("{$from}/storage/{$path}");
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $disk->put($path, $response->body());
        $this->counts['downloaded']++;

        return $response->body();
    }

    private function missing(string $path): void
    {
        $this->counts['missing']++;
        $this->warn("  missing    {$path}");
    }
}
