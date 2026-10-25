<?php

namespace App\Enums;

/**
 * What a guide's image is for. The slot decides what an upload must be, which crops are cut from
 * it and how many it holds (`maxImages()`); a shape one master cannot give would be another slot,
 * not another column.
 *
 * `COVER` is one landscape master that every surface is cut from: the link preview, the page's
 * hero and the "My guides" thumbnail. Its floor is the share card's width without upscaling, and
 * a square crop well above the thumbnail's size; its ratio band admits 3:2 and 16:9 alike.
 *
 * `FLYER` is a social media image that sells the guide, as its flyer prompt asks for it: 1:1,
 * 4:5 or 9:16, at least 1024 px on its short side — an image generator's 1024 × 1024 or
 * 1024 × 1536, a little under a post's 1080, which a network scales up without loss you can see. A guide keeps several — one per network, per
 * campaign, per prompt version — each added beside the others. It is posted as it is, so it is
 * never cut: its one crop is the whole frame, scaled down to a post's width.
 */
enum ImageSlot: string
{
    case COVER = 'cover';
    case FLYER = 'flyer';

    /** The largest upload, in kilobytes — the PHP limit production was given for it. */
    public const MAX_KILOBYTES = 12 * 1024;

    public function label(): string
    {
        return __("arcadia.editor.images.slots.{$this->value}.label");
    }

    public function minWidth(): int
    {
        return match ($this) {
            self::COVER => 1200,
            self::FLYER => 1024,
        };
    }

    public function minHeight(): int
    {
        return match ($this) {
            self::COVER => 675,
            self::FLYER => 1024,
        };
    }

    /**
     * How many images it holds. A slot of one replaces its image on upload; a slot of more adds
     * each upload beside the others, until it is full.
     */
    public function maxImages(): int
    {
        return match ($this) {
            self::COVER => 1,
            self::FLYER => 12,
        };
    }

    public function holdsMany(): bool
    {
        return $this->maxImages() > 1;
    }

    /**
     * Width ÷ height, inclusive at both ends.
     *
     * @return array{0: float, 1: float}
     */
    public function ratioRange(): array
    {
        return match ($this) {
            self::COVER => [1.4, 1.91],
            // 9:16 is 0.5625; a generator's 1024 × 1792 is 0.571.
            self::FLYER => [0.55, 1.0],
        };
    }

    /**
     * The crops cut from it, by name: a fixed `width` × `height`, cut from the centre; or a
     * `width` alone, the whole frame scaled down to it and never up.
     *
     * @return array<string, array{width: int, height: int|null}>
     */
    public function crops(): array
    {
        return match ($this) {
            self::COVER => [
                // The link preview (`summary_large_image`, 1.91:1), and the editor's guide card.
                'share' => ['width' => 1200, 'height' => 630],
                // The guide page and the presentation page.
                'hero' => ['width' => 1600, 'height' => null],
                // A "My guides" card, at twice its size.
                'thumb' => ['width' => 320, 'height' => 320],
            ],
            self::FLYER => [
                // The whole flyer at a post's width, never upscaled: a 1024 px flyer stays 1024.
                'post' => ['width' => 1080, 'height' => null],
            ],
        };
    }

    /**
     * What the editor shows beside the upload: the rule, and whether it is filled.
     *
     * @return array{slot: string, label: string, min_width: int, min_height: int, ratio: array{0: float, 1: float}, max_mb: int, max_images: int}
     */
    public function rules(): array
    {
        return [
            'slot' => $this->value,
            'label' => $this->label(),
            'min_width' => $this->minWidth(),
            'min_height' => $this->minHeight(),
            'ratio' => $this->ratioRange(),
            'max_mb' => intdiv(self::MAX_KILOBYTES, 1024),
            'max_images' => $this->maxImages(),
        ];
    }
}
