<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Mute;

use App\Filesystem\ProfileImagesFilesystem;
use App\Models\Folder;
use App\Models\User;
use App\Services\Folder\MuteCollaboratorService;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\AssertValidPaginationData;
use Tests\TestCase;
use Tests\Traits\GeneratesId;

class FetchMutedCollaboratorsTest extends TestCase
{
    use AssertValidPaginationData;
    use GeneratesId;

    protected MuteCollaboratorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MuteCollaboratorService::class);
    }

    protected function fetchMuteCollaboratorsResponse(array $parameters = []): TestResponse
    {
        foreach (['folder_id', 'page', 'per_page'] as $key) {
            if (array_key_exists($key, $parameters)) {
                $parameters[$key] = (string) $parameters[$key];
            }
        }

        return $this->getJson(route('fetchMutedCollaborator', $parameters));
    }

    #[Test]
    public function uri(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/mute', 'fetchMutedCollaborator');
    }

    #[Test]
    public function willReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->fetchMuteCollaboratorsResponse(['folder_id' => 4])->assertUnauthorized();
    }

    #[Test]
    public function willReturnUnprocessableWhenParameterAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->fetchMuteCollaboratorsResponse(['folder_id' => 44])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound',]);

        $this->fetchMuteCollaboratorsResponse(['folder_id' => $this->generateFolderId()->present(), 'name' => str_repeat('r', 11)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'The name must not be greater than 10 characters.',]);

        $this->assertValidPaginationData($this, 'fetchMutedCollaborator', ['folder_id' => $this->generateFolderId()->present()]);
    }

    #[Test]
    public function fetchCollaborators(): void
    {
        [$folderOwner, $otherCollaborator] = UserFactory::times(2)->create();

        $collaborator = UserFactory::new()->hasProfileImage()->create();

        $folders = FolderFactory::times(2)->for($folderOwner)->create();

        $this->service->mute($folders[0]->id, $collaborator->id, $folderOwner->id);
        $this->service->mute($folders[1]->id, $otherCollaborator->id, $folderOwner->id);

        $this->loginUser($folderOwner);
        $this->fetchMuteCollaboratorsResponse(['folder_id' => $folders[0]->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(3, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.id', $collaborator->public_id->present())
            ->assertJsonPath('data.0.attributes.profile_image_url', (new ProfileImagesFilesystem())->publicUrl($collaborator->profile_image_path))
            ->assertJsonPath('data.0.attributes.name', "{$collaborator->first_name} {$collaborator->last_name}")
            ->assertJsonPath('data.0.type', 'mutedCollaborator')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'type',
                        'attributes' => [
                            'id',
                            'name',
                            'profile_image_url'
                        ]
                    ]
                ]
            ]);
    }

    #[Test]
    public function willSortByLatest(): void
    {
        [$folderOwner, $collaborator, $otherCollaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->service->mute($folder->id, $collaborator->id, $folderOwner->id);
        $this->service->mute($folder->id, $otherCollaborator->id, $folderOwner->id);

        $this->loginUser($folderOwner);
        $this->fetchMuteCollaboratorsResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.attributes.id', $otherCollaborator->public_id->present())
            ->assertJsonPath('data.1.attributes.id', $collaborator->public_id->present());
    }

    #[Test]
    public function willNotIncludeDeleteCollaboratorAccounts(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->service->mute($folder->id, $collaborator->id, $folderOwner->id);

        $collaborator->delete();

        $this->loginUser($folderOwner);
        $this->fetchMuteCollaboratorsResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function willReturnOnlyCollaboratorsWithSpecifiedName(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        /** @var User[] */
        $collaborators = UserFactory::times(3)
            ->sequence(
                ['first_name' => 'Bryan'],
                ['first_name' => 'Bryan'],
                ['first_name' => 'Jack']
            )
            ->create();

        /** @var Folder[] */
        $userFolders = FolderFactory::times(2)->for($user)->create();

        $this->service->mute($userFolders[0]->id, $collaborators[0]->id, $user->id);
        $this->service->mute($userFolders[1]->id, $collaborators[1]->id, $user->id);
        $this->service->mute($userFolders[0]->id, $collaborators[2]->id, $user->id);

        $this->fetchMuteCollaboratorsResponse(['folder_id' => $userFolders[0]->public_id->present(), 'name' => 'bryan'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $collaborators[0]->public_id->present());

        $this->fetchMuteCollaboratorsResponse(['folder_id' => $userFolders[1]->public_id->present(), 'name' => 'bryan'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $collaborators[1]->public_id->present());
    }

    #[Test]
    public function whenUserHasNotMutedAnyCollaborator(): void
    {
        $user = UserFactory::new()->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->loginUser($user);
        $this->fetchMuteCollaboratorsResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        $folderOwner = UserFactory::new()->create();

        $folder = FolderFactory::new()->create();

        $this->loginUser($folderOwner);
        $this->fetchMuteCollaboratorsResponse(['folder_id' => $folder->public_id->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $folderOwner = UserFactory::new()->create();

        $this->loginUser($folderOwner);
        $this->fetchMuteCollaboratorsResponse(['folder_id' => $this->generateFolderId()->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    #[Test]
    public function whenCollaboratorMuteDurationIsPast(): void
    {
        [$folderOwner, $collaborator, $otherCollaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->service->mute($folder->id, $collaborator->id, $folderOwner->id, now(), 1);
        $this->service->mute($folder->id, $otherCollaborator->id, $folderOwner->id);

        $this->loginUser($folderOwner);
        $this->travel(55)->minutes(function () use ($folder) {
            $this->fetchMuteCollaboratorsResponse(['folder_id' => $folder->public_id->present()])
                ->assertOk()
                ->assertJsonCount(2, 'data');
        });

        $this->travel(61)->minutes(function () use ($folder, $otherCollaborator) {
            $this->fetchMuteCollaboratorsResponse(['folder_id' => $folder->public_id->present()])
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.attributes.id', $otherCollaborator->public_id->present());
        });
    }
}
