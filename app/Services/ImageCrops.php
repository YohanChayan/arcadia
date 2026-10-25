<?php

namespace App\Services;

use GdImage;
use RuntimeException;

/**
 * Cuts a slot's crops from an uploaded image, with GD, as JPEG. Pixels only: where they are kept
 * is `StoreGuideImage`'s.
 *
 * JPEG, not WebP: every crawler that fetches a link preview reads it, and it is the one format
 * both the Docker image's GD and production's were known to write. A transparent upload is laid on
 * Arcadia's navy, the colour it is shown on, rather than JPEG's default black.
 */
class ImageCrops
{
    private const QUALITY = 82;

    /** Arcadia's ground, `#030D1C` (ADR 0019). */
    private const BACKGROUND = [3, 13, 28];

    /**
     * @param  array<string, array{width: int, height: int|null}>  $crops  as `ImageSlot::crops()`
     * @return array<string, string> each crop's JPEG bytes, by name
     */
    public function cut(string $contents, array $crops): array
    {
        $source = @imagecreatefromstring($contents);

        if (! $source instanceof GdImage) {
            throw new RuntimeException('The image could not be read.');
        }

        try {
            return collect($crops)
                ->map(fn (array $crop) => $this->jpeg($crop['height'] === null
                    ? $this->scaled($source, $crop['width'])
                    : $this->centred($source, $crop['width'], $crop['height'])))
                ->all();
        } finally {
            imagedestroy($source);
        }
    }

    /** The largest centred region of the source with the target's shape, resized to it. */
    private function centred(GdImage $source, int $width, int $height): GdImage
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        // Wider than the target: keep the full height and cut the sides; else the full width.
        if ($sourceWidth * $height > $sourceHeight * $width) {
            $cutHeight = $sourceHeight;
            $cutWidth = (int) round($sourceHeight * $width / $height);
        } else {
            $cutWidth = $sourceWidth;
            $cutHeight = (int) round($sourceWidth * $height / $width);
        }

        $target = $this->canvas($width, $height);
        imagecopyresampled(
            $target, $source,
            0, 0, intdiv($sourceWidth - $cutWidth, 2), intdiv($sourceHeight - $cutHeight, 2),
            $width, $height, $cutWidth, $cutHeight,
        );

        return $target;
    }

    /** The whole frame, scaled down to the width — never up. */
    private function scaled(GdImage $source, int $maxWidth): GdImage
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $width = min($maxWidth, $sourceWidth);
        $height = (int) round($sourceHeight * $width / $sourceWidth);

        $target = $this->canvas($width, $height);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        return $target;
    }

    private function canvas(int $width, int $height): GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, ...self::BACKGROUND));

        return $canvas;
    }

    private function jpeg(GdImage $image): string
    {
        ob_start();
        imageinterlace($image, true);
        imagejpeg($image, null, self::QUALITY);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
