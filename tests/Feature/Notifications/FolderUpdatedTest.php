<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Folder;
use App\Notifications\FolderDescriptionUpdatedNotification;
use App\Notifications\FolderIconUpdatedNotification;
use App\Notifications\FolderNameUpdatedNotification;
use Tests\TestCase;
use Illuminate\Support\Str;
use Database\Factories\UserFactory;
use Database\Factories\FolderFactory;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;

class FolderUpdatedTest extends TestCase
{
    use MakesHttpRequest;
    use WithFaker;

    #[Test]
    public function nameUpdatedNotification(): void
    {
        $folderOwner = UserFactory::new()->create();
        $collaborator = UserFactory::new()->hasProfileImage()->create();

        $folder = FolderFactory::new()->for($folderOwner)->create(['name' => 'foo']);
        $folder->name = 'baz';

        $folderOwner->notify(
            new FolderNameUpdatedNotification($folder, $collaborator)
        );

        $collaborator->update(['first_name' => 'john', 'last_name' => 'doe']);

        $folder->update(['name' => 'john doe problems']);
        $expectedDateTime = $folderOwner->notifications()->sole(['created_at'])->created_at;

        $this->loginUser($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'FolderUpdatedNotification')
            ->assertJsonPath('data.0.attributes.collaborator_exists', true)
            ->assertJsonPath('data.0.attributes.folder_exists', true)
            ->assertJsonPath('data.0.attributes.message', 'John Doe changed John Doe Problems name from Foo to Baz.')
            ->assertJsonPath('data.0.attributes.id', fn (string $id) => Str::isUuid($id))
            ->assertJsonPath('data.0.attributes.notified_on', fn (string $dateTime) => $dateTime === (string) $expectedDateTime)
            ->assertJsonPath('data.0.attributes.collaborator_id', $collaborator->id)
            ->assertJsonPath('data.0.attributes.folder_id', $folder->id)
            ->assertJsonCount(7, 'data.0.attributes')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'type',
                        'attributes' => [
                            'id',
                            'collaborator_exists',
                            'folder_exists',
                            'notified_on',
                            'collaborator_id',
                            'folder_id',
                            'message',
                        ]
                    ]
                ]
            ]);
    }

    #[Test]
    public function descriptionUpdatedNotification(): void
    {
        $folderOwner = UserFactory::new()->create();
        $collaborator = UserFactory::new()->create(['first_name' => 'john', 'last_name' => 'doe']);

        $folder = FolderFactory::new()->for($folderOwner)->create(['description' => 'foo']);
        $folder->description = $newDescription = Str::limit($this->faker->words(150, true), 150, '');

        $folderOwner->notify(
            new FolderDescriptionUpdatedNotification($folder, $collaborator)
        );

        $this->loginUser($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonPath('data.0.attributes.message', "John Doe changed {$folder->name->present()} description from Foo to {$newDescription}.");
    }

    #[Test]
    public function thumbnailUpdatedNotification(): void
    {
        $folderOwner = UserFactory::new()->create();
        $collaborator = UserFactory::new()->create(['first_name' => 'john', 'last_name' => 'doe']);

        $folder = FolderFactory::new()->for($folderOwner)->create(['description' => 'foo']);
        $folder->icon_path = Str::random(40) . 'jpg';

        $folderOwner->notify(
            new FolderIconUpdatedNotification($folder, $collaborator)
        );

        $this->loginUser($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonPath('data.0.attributes.message', "John Doe changed {$folder->name->present()} icon.");
    }

    public function testWillReturnCorrectPayloadWhenFolderDoesNoLongerExists(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        /** @var Folder */
        $folder = FolderFactory::new()->for($folderOwner)->create(['name' => 'foo']);
        $folder->name = 'baz';

        $folderOwner->notify(
            new FolderNameUpdatedNotification($folder, $collaborator)
        );

        $folder->delete();

        $this->loginUser($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonPath('data.0.attributes.folder_exists', false)
            ->assertJsonPath('data.0.attributes.message', "{$collaborator->full_name->present()} changed Foo name from Foo to Baz.");
    }

    public function testWillReturnCorrectPayloadWhenCollaboratorNoLongerExists(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create(['first_name' => 'john', 'last_name' => 'doe']);

        /** @var Folder */
        $folder = FolderFactory::new()->for($folderOwner)->create(['name' => 'foo']);
        $folder->name = 'baz';

        $folderOwner->notify(
            new FolderNameUpdatedNotification($folder, $collaborator)
        );

        $collaborator->delete();

        $this->loginUser($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonPath('data.0.attributes.collaborator_exists', false)
            ->assertJsonPath('data.0.attributes.message', "John Doe changed Foo name from Foo to Baz.");
    }
}
