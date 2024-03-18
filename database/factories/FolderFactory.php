<?php

declare(strict_types=1);

namespace Database\Factories;

use App\DataTransferObjects\Builders\FolderSettingsBuilder;
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
            'settings'    => [],
            'password'    => null
        ];
    }

    public function private(): self
    {
        return $this->state(['visibility' => FolderVisibility::PRIVATE]);
    }

    public function visibleToCollaboratorsOnly(): self
    {
        return $this->state(['visibility' => FolderVisibility::COLLABORATORS]);
    }

    public function passwordProtected(string $password = 'password'): self
    {
        return $this->state([
            'visibility' => FolderVisibility::PASSWORD_PROTECTED,
            'password'   => $password
        ]);
    }

    public function settings(FolderSettingsBuilder $settings): self
    {
        return $this->state(['settings' => $settings->build()]);
    }
}
