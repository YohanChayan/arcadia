<?php

namespace Tests\Feature;

use App\Enums\ImageSlot;
use App\Enums\Level;
use App\Models\Guide;
use App\Models\GuideImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Imported `guide_images` rows arrive without their files; `arcadia:fetch-images` brings each one
 * from the old host to the path its row names.
 */
class FetchGuideImagesTest extends TestCase
{
    use RefreshDatabase;

    private const FROM = 'https://arcadia.yhndevside.com';

    private Guide $guide;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(GuideImage::DISK);
        Http::preventStrayRequests();

        $this->guide = Guide::create(['locale' => 'es', 'title' => 'Excel para el trabajo', 'level' => Level::BASIC]);
    }

    private function cover(string $directory): GuideImage
    {
        return $this->guide->images()->create([
            'slot' => ImageSlot::COVER,
            'directory' => $directory,
            'filename' => 'original.jpg',
            'width' => 1536,
            'height' => 1024,
            'bytes' => 1,
        ]);
    }

    public function test_it_downloads_each_file_keeps_what_is_there_and_cuts_a_crop_the_old_host_lacks(): void
    {
        $original = UploadedFile::fake()->image('cover.jpg', 1536, 1024)->getContent();
        $disk = Storage::disk(GuideImage::DISK);
        $disk->put('studia/guides/1/abc/thumb.jpg', 'already here');
        $this->cover('studia/guides/1/abc');

        Http::fake([
            self::FROM.'/storage/studia/guides/1/abc/original.jpg' => Http::response($original),
            self::FROM.'/storage/studia/guides/1/abc/share.jpg' => Http::response('share bytes'),
            '*' => Http::response('', 404),
        ]);

        $this->artisan('arcadia:fetch-images', ['from' => self::FROM.'/'])->assertSuccessful();

        $this->assertSame($original, $disk->get('studia/guides/1/abc/original.jpg'));
        $this->assertSame('share bytes', $disk->get('studia/guides/1/abc/share.jpg'));
        $this->assertSame('already here', $disk->get('studia/guides/1/abc/thumb.jpg'));
        // `hero` is 1600 wide with a free height, and never upscaled past the original.
        $this->assertSame(1536, getimagesizefromstring($disk->get('studia/guides/1/abc/hero.jpg'))[0]);
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), 'thumb.jpg'));
    }

    public function test_an_image_the_old_host_does_not_have_is_reported_and_fails_the_run(): void
    {
        $this->cover('guides/1/gone');

        Http::fake(['*' => Http::response('', 404)]);

        $this->artisan('arcadia:fetch-images', ['from' => self::FROM])
            ->expectsOutputToContain('missing    guides/1/gone/original.jpg')
            ->assertFailed();

        Storage::disk(GuideImage::DISK)->assertDirectoryEmpty('/');
    }
}
