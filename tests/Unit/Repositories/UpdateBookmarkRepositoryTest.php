<?php

namespace Tests\Unit\Repositories;

use App\DataTransferObjects\Builders\BookmarkBuilder as Builder;
use App\DataTransferObjects\UpdateBookmarkData as Data;
use App\Models\Bookmark;
use App\Repositories\UpdateBookmarkRepository;
use App\Utils\UrlHasher;
use App\ValueObjects\Url;
use Database\Factories\BookmarkFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UpdateBookmarkRepositoryTest extends TestCase
{
    use WithFaker;

    public function testUpdateBookmark(): void
    {
        $hasher = new UrlHasher;

        $this->assertUpdatedAttributesEquals(Builder::new()->tags([]), function (array $updatedAttributes) {
            $this->assertEmpty($updatedAttributes);
        });

        $this->assertUpdatedAttributesEquals(Builder::new()->title('foo'), function (array $updatedAttributes) {
            $this->assertEquals($updatedAttributes, [
                'title' => 'foo',
                'has_custom_title' => true
            ]);
        });

        $this->assertUpdatedAttributesEquals(Builder::new()->description('description'), function (array $updatedAttributes) {
            $this->assertEquals($updatedAttributes, [
                'description' => 'description',
                'description_set_by_user' => true
            ]);
        });

        $this->assertUpdatedAttributesEquals(
            Builder::new()->thumbnailUrl(new Url('https://www.images.com/tv/foo.png')),
            function (array $updatedAttributes) {
                $this->assertEquals($updatedAttributes, [
                    'preview_image_url' => 'https://www.images.com/tv/foo.png',
                ]);
            }
        );

        $this->assertUpdatedAttributesEquals(
            Builder::new()->canonicalUrl(new Url('https://www.rottentomatoes.com/tv/resident_evil')),
            function (array $updatedAttributes) {
                $this->assertEquals($updatedAttributes, [
                    'url_canonical' => 'https://www.rottentomatoes.com/tv/resident_evil',
                ]);
            }
        );

        $this->assertUpdatedAttributesEquals(
            Builder::new()->canonicalUrlHash($hash = $hasher->hashUrl(new Url($this->faker->url))),
            function (array $updatedAttributes) use ($hash) {
                $this->assertEquals($updatedAttributes, [
                    'url_canonical_hash' => (string) $hash
                ]);
            }
        );

        $this->assertUpdatedAttributesEquals(
            Builder::new()->resolvedUrl(new Url('https://www.rottentomatoes.com/celebrity/taika_waititi')),
            function (array $updatedAttributes) {
                $this->assertEquals($updatedAttributes, [
                    'resolved_url' => 'https://www.rottentomatoes.com/celebrity/taika_waititi',
                ]);
            }
        );

        $this->assertUpdatedAttributesEquals(
            Builder::new()->resolvedAt(now()),
            function (array $updatedAttributes) {
                $this->assertCount(1, $updatedAttributes);
                $this->assertArrayHasKey('resolved_at', $updatedAttributes);
            }
        );
    }

    private function assertUpdatedAttributesEquals(Builder $updateData, \Closure $assertFn)
    {
        /** @var UpdateBookmarkRepository */
        $repository = app(UpdateBookmarkRepository::class);

        /** @var Bookmark */
        $model = BookmarkFactory::new()->create();

        $repository->update(new Data($updateData->id($model->id)->build()));

        /** @var Bookmark */
        $updatedBookmark = Bookmark::query()->whereKey($model->id)->first();

        $updatedBookmark->offsetUnset('updated_at');
        $model->offsetUnset('updated_at');

        $assertFn(
            array_diff_assoc($updatedBookmark->toArray(), $model->toArray())
        );
    }
}
