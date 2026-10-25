<?php

namespace App\Actions;

use App\Enums\ImageSlot;
use App\Models\Guide;
use App\Models\GuideImage;
use App\Services\ImageCrops;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Puts an upload in a guide's slot: the original as it came and its crops, in a new directory
 * under a random name, so a cover cannot be guessed and a cached preview of the previous one is
 * never served as this one. A slot of one image replaces it: the previous one's files go once the
 * new ones are in place. A slot of many adds the upload beside the others. The upload is assumed
 * checked (`GuideImageRequest`), room in its slot included.
 *
 * Under `guides/`, named for the thing and not the brand (2026-10-21), so a rename never moves a
 * file. Covers uploaded while the product was Studia stay under `studia/guides/`: each row keeps
 * its own `directory`, and the files and the previews already cached by URL keep working.
 */
class StoreGuideImage
{
    public function __construct(private readonly ImageCrops $crops) {}

    public function handle(Guide $guide, ImageSlot $slot, UploadedFile $upload): GuideImage
    {
        $disk = Storage::disk(GuideImage::DISK);
        $directory = "guides/{$guide->id}/".Str::lower(Str::random(24));
        $filename = 'original.'.$upload->guessExtension();
        $contents = $upload->getContent();
        [$width, $height] = getimagesizefromstring($contents);

        try {
            $disk->put("{$directory}/{$filename}", $contents);

            foreach ($this->crops->cut($contents, $slot->crops()) as $crop => $jpeg) {
                $disk->put("{$directory}/{$crop}.jpg", $jpeg);
            }
        } catch (Throwable $exception) {
            $disk->deleteDirectory($directory);

            throw $exception;
        }

        $image = $slot->holdsMany()
            ? $guide->images()->make(['slot' => $slot])
            : $guide->images()->firstOrNew(['slot' => $slot]);
        $previous = $image->exists ? $image->directory : null;

        $image->fill([
            'directory' => $directory,
            'filename' => $filename,
            'width' => $width,
            'height' => $height,
            'bytes' => strlen($contents),
        ])->save();

        if ($previous !== null) {
            $disk->deleteDirectory($previous);
        }

        return $image;
    }
}
