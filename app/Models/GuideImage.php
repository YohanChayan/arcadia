<?php

namespace App\Models;

use App\Enums\ImageSlot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One of a guide's images: the owner's upload and its crops, in one directory on the `public`
 * disk. Deleting the row deletes the directory. See the `create_guide_images_table` migration.
 */
class GuideImage extends Model
{
    public const DISK = 'public';

    protected $table = 'guide_images';

    protected $fillable = ['guide_id', 'slot', 'directory', 'filename', 'width', 'height', 'bytes'];

    protected $casts = [
        'slot' => ImageSlot::class,
    ];

    protected static function booted(): void
    {
        static::deleted(function (GuideImage $image) {
            Storage::disk(self::DISK)->deleteDirectory($image->directory);
        });
    }

    public function guide(): BelongsTo
    {
        return $this->belongsTo(Guide::class);
    }

    /**
     * A crop's address, or the original's with no crop — root-relative, because the share card is
     * prefixed with the site's own host (`SeoHead`).
     */
    public function url(?string $crop = null): string
    {
        return '/storage/'.$this->path($crop);
    }

    /** A crop's path on the disk, or the original's with no crop. */
    public function path(?string $crop = null): string
    {
        return $this->directory.'/'.($crop === null ? $this->filename : "{$crop}.jpg");
    }

    /**
     * A crop as a page shows it: its address, and its size, so the page holds its place before it
     * loads. A fixed crop is its slot's size; a scaled one keeps the original's shape.
     *
     * @return array{url: string, width: int, height: int}
     */
    public function toPage(string $crop): array
    {
        ['width' => $width, 'height' => $height] = $this->slot->crops()[$crop];
        $width = $height === null ? min($width, $this->width) : $width;

        return [
            'url' => $this->url($crop),
            'width' => $width,
            'height' => $height ?? (int) round($this->height * $width / $this->width),
        ];
    }

    /** What the editor shows of it. */
    public function toEditor(): array
    {
        return [
            'id' => $this->id,
            'original' => $this->url(),
            'crops' => collect($this->slot->crops())->keys()->mapWithKeys(fn (string $crop) => [$crop => $this->url($crop)])->all(),
            'width' => $this->width,
            'height' => $this->height,
            'bytes' => $this->bytes,
            'updated_at' => $this->updated_at,
        ];
    }
}
