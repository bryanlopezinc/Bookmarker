<?php

declare(strict_types=1);

namespace Database\Factories;

use App\DataTransferObjects\Builders\FolderSettingsBuilder;
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
        return $this->state(['is_public' => true]);
    }

    /**
     * @param \Closure(FolderSettingsBuilder):FolderSettingsBuilder $setting
     */
    public function setting(\Closure $setting): self
    {
        return $this->state(function (array $data) use ($setting) {
            $setting($builder = new FolderSettingsBuilder($data['settings']));
            $data['settings'] = $builder->build()->toArray();

            return $data;
        });
    }
}
