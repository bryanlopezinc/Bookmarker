<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Notifications\FolderDescriptionChangedNotification;
use Tests\TestCase;
use Illuminate\Support\Str;
use Database\Factories\UserFactory;
use Database\Factories\FolderFactory;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;

class FolderDescriptionChangedTest extends TestCase
{
    use MakesHttpRequest;
    use WithFaker;

    #[Test]
    public function descriptionChangedNotification(): void
    {
        $folderOwner = UserFactory::new()->create();
        $collaborator = UserFactory::new()->hasProfileImage()->create();

        $folder = FolderFactory::new()->for($folderOwner)->create(['description' => 'foo']);
        $folder->description = 'baz';

        $folderOwner->notify(
            new FolderDescriptionChangedNotification($folder, $collaborator)
        );

        $folder->save();

        $collaborator->update(['first_name' => 'john', 'last_name' => 'doe']);

        $this->loginUser($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'FolderDescriptionChangedNotification')
            ->assertJsonPath('data.0.attributes.collaborator.exists', true)
            ->assertJsonPath('data.0.attributes.folder.exists', true)
            ->assertJsonPath('data.0.attributes.message', 'John Doe changed folder description from foo to baz')
            ->assertJsonPath('data.0.attributes.id', fn (string $id) => Str::isUuid($id))
            ->assertJsonPath('data.0.attributes.collaborator.id', $collaborator->public_id->present())
            ->assertJsonPath('data.0.attributes.folder.id', $folder->public_id->present())
            ->assertJsonCount(5, 'data.0.attributes')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'type',
                        'attributes' => [
                            'id',
                            'message',
                            'notified_on',
                            'collaborator' => [
                                'id',
                                'exists'
                            ],
                            'folder' => [
                                'id',
                                'exists',
                            ],
                        ]
                    ]
                ]
            ]);
    }

    #[Test]
    public function whenDescriptionWasChangedFromBlankToFilled(): void
    {
        $folderOwner = UserFactory::new()->create();
        $collaborator = UserFactory::new()->create(['first_name' => 'jack', 'last_name' => 'sparrow']);

        $folder = FolderFactory::new()->for($folderOwner)->create(['description' => null]);
        $folder->description = 'baz';

        $folderOwner->notify(
            new FolderDescriptionChangedNotification($folder, $collaborator)
        );

        $folder->save();

        $this->loginUser($folderOwner);
        $this->fetchNotificationsResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.message', 'Jack Sparrow changed folder description to baz');
    }

    #[Test]
    public function whenDescriptionWasChangedFromFilledToBlank(): void
    {
        $folderOwner = UserFactory::new()->create();
        $collaborator = UserFactory::new()->create(['first_name' => 'jack', 'last_name' => 'sparrow']);

        $folder = FolderFactory::new()->for($folderOwner)->create();
        $folder->description = null;

        $folderOwner->notify(
            new FolderDescriptionChangedNotification($folder, $collaborator)
        );

        $folder->save();

        $this->loginUser($folderOwner);
        $this->fetchNotificationsResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.message', 'Jack Sparrow removed folder description');
    }
}
