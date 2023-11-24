<?php

declare(strict_types=1);

namespace Database\Factories;

use App\DataTransferObjects\Builders\FolderSettingsBuilder;
use App\ValueObjects\FolderSettings;
use App\Enums\FolderVisibility;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Folder>
 */
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
            'name'        => $this->faker->word,
            'description' => $this->faker->sentence,
            'user_id'     => UserFactory::new(),
            'visibility'  => FolderVisibility::PUBLIC,
            'settings'    => new FolderSettings([]),
        ];
    }

    public function private(): self
    {
        return $this->state(['visibility' => FolderVisibility::PRIVATE]);
    }

    public function settings(FolderSettingsBuilder $settings): self
    {
        return $this->state(['settings' => $settings->build()]);
    }
}
