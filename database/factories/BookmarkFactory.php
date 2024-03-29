<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BookmarkCreationSource;
use App\Models\Bookmark;
use App\Utils\UrlHasher;
use App\ValueObjects\Url;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bookmark>
 */
final class BookmarkFactory extends Factory
{
    protected $model = Bookmark::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $hasher = new UrlHasher();

        return [
            'title'                   => $url = $this->faker->url(),
            'has_custom_title'        => false,
            'url'                     => $url,
            'description'             => $this->faker->sentence,
            'description_set_by_user' => false,
            'preview_image_url'       => $this->faker->url,
            'source_id'               => SourceFactory::new()->create()->id,
            'user_id'                 => UserFactory::new(),
            'url_canonical'           => $url,
            'url_canonical_hash'      => $hasher->hashUrl(new Url($url)),
            'resolved_url'            => $url,
            'resolved_at'             => null,
            'created_from'            => BookmarkCreationSource::HTTP
        ];
    }
}
