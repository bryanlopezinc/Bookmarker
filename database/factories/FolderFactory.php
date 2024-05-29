<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Contracts\IdGeneratorInterface;
use App\Models\Folder;
use Illuminate\Support\Str;
use App\Enums\FolderVisibility;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\FolderSettings\FolderSettings;
use App\FolderSettings\SettingInterface;
use Illuminate\Support\Arr;

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
        /** @var IdGeneratorInterface */
        $IdGenerator = app(IdGeneratorInterface::class);

        return [
            'public_id'   => $IdGenerator->generate(),
            'name'        => $this->faker->word,
            'description' => $this->faker->sentence,
            'user_id'     => UserFactory::new(),
            'visibility'  => FolderVisibility::PUBLIC,
            'settings'    => [],
            'password'    => null,
            'icon_path'   => null
        ];
    }

    public function hasCustomIcon(string $iconPath = null): self
    {
        return $this->state(['icon_path' => $iconPath ?? Str::random(40) . 'jpg']);
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

    /**
     * @param array<SettingInterface>|SettingInterface $settings settings
     */
    public function settings(array|SettingInterface $settings): self
    {
        return $this->state(['settings' => FolderSettings::fromKeys(Arr::wrap($settings))]);
    }
}
