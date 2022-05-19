<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UserResourcesCount;
use Illuminate\Database\Eloquent\Factories\Factory;

final class UserResourceCountFactory extends Factory
{
    protected $model = UserResourcesCount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
        ];
    }

    public function bookmark(): self
    {
        return $this->state([
            'type' => UserResourcesCount::BOOKMARKS_TYPE,
        ]);
    }

    public function favourite(): self
    {
        return $this->state([
            'type' => UserResourcesCount::FAVOURITES_TYPE,
        ]);
    }
}
