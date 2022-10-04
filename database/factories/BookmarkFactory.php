<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Contracts\UrlHasherInterface;
use App\Models\Bookmark;
use App\ValueObjects\Url;
use Illuminate\Database\Eloquent\Factories\Factory;

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
        /** @var UrlHasherInterface */
        $hasher = app(UrlHasherInterface::class);

        return [
            'title' => $url = $this->faker->url(),
            'has_custom_title' => false,
            'url'  => $url,
            'description' => $this->faker->sentence,
            'description_set_by_user' => false,
            'preview_image_url' => $this->faker->url,
            'site_id' => SourceFactory::new()->create()->id,
            'user_id' => UserFactory::new()->create()->id,
            'url_canonical' => $url,
            'url_canonical_hash' => (string) $hasher->hashUrl(new Url($url)),
            'resolved_url' => $url,
            'resolved_at' => null
        ];
    }
}
