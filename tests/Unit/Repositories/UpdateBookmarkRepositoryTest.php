<?php

namespace Tests\Unit\Repositories;

use App\Contracts\UrlHasherInterface;
use App\DataTransferObjects\Builders\UpdateBookmarkDataBuilder as Builder;
use App\Models\Bookmark;
use App\Repositories\UpdateBookmarkRepository;
use App\ValueObjects\Url;
use Database\Factories\BookmarkFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UpdateBookmarkRepositoryTest extends TestCase
{
    use WithFaker;

    public function testUpdateBookmark(): void
    {
        /** @var UrlHasherInterface */
        $hasher = app(UrlHasherInterface::class);

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
            Builder::new()->previewImageUrl(new Url('https://www.images.com/tv/foo.png')),
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
            Builder::new()->canonicalUrlHash($hash = $hasher->hashCanonicalUrl(new Url($this->faker->url))),
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
    }

    private function assertUpdatedAttributesEquals(Builder $updateData, \Closure $assertFn)
    {
        /** @var UpdateBookmarkRepository */
        $repository = app(UpdateBookmarkRepository::class);

        /** @var Bookmark */
        $model = BookmarkFactory::new()->create();

        $repository->update($updateData->id($model->id)->build());

        /** @var Bookmark */
        $updatedBookmark = Bookmark::query()->whereKey($model->id)->first();

        $updatedBookmark->offsetUnset('updated_at');
        $model->offsetUnset('updated_at');

        $updatedAttributes = array_diff($updatedBookmark->toArray(), $model->toArray());

        $assertFn($updatedAttributes);
    }
}
