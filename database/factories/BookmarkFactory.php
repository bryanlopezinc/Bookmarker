<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Bookmark;
use App\Models\Tag;
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
        return [
            'title' => $url = $this->faker->url(),
            'has_custom_title' => false,
            'url'  => $url,
            'description' => $this->faker->sentence,
            'description_set_by_user' => false,
            'preview_image_url' => $this->faker->url,
            'site_id' => SiteFactory::new()->create()->id,
            'user_id' => UserFactory::new()->create()->id
        ];
    }
}
