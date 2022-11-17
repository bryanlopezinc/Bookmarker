<?php

declare(strict_types=1);

namespace Database\Factories;

use App\DataTransferObjects\FolderSettings;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Factories\Factory;

final class FolderFactory extends Factory
{
    protected $model = Folder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => $this->faker->word,
            'description' => $this->faker->sentence,
            'user_id' => UserFactory::new()->create()->id,
            'is_public' => false,
            'settings' => FolderSettings::default()->toArray()
        ];
    }

    public function public(): self
    {
        return $this->state([
            'is_public' => true,
        ]);
    }
}
