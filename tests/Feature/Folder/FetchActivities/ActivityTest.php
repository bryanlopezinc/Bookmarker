<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\FetchActivities;

use App\Actions\CreateFolderActivity;
use App\Enums\ActivityType as Type;
use App\DataTransferObjects\Activities\FolderIconChangedActivityLogData;
use App\DataTransferObjects\Activities\FolderNameChangedActivityLogData;
use App\Enums\FolderActivitiesVisibility as Visibility;
use App\FolderSettings\Settings\Activities\ActivitiesVisibility;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\AssertValidPaginationData;
use Tests\Traits\GeneratesId;

class ActivityTest extends TestCase
{
    use GeneratesId;
    use AssertValidPaginationData;

    #[Test]
    public function path(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/activities', 'fetchFolderActivities');
    }

    #[Test]
    public function willReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->fetchActivitiesTestResponse(['folder_id' => 5])->assertUnauthorized();
    }

    #[Test]
    public function whenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->fetchActivitiesTestResponse(['folder_id' => $this->generateFolderId()->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->assertValidPaginationData($this, 'fetchFolderActivities', ['folder_id' => $this->generateFolderId()->present()]);
    }

    #[Test]
    public function whenActivitiesVisibilityIsPublic(): void
    {
        [$user, $collaborator, $folderOwner] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(new ActivitiesVisibility(Visibility::PUBLIC))
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->loginUser($user);
        $this->fetchActivitiesTestResponse($query = ['folder_id' => $folder->public_id->present()])->assertOk();

        $this->loginUser($collaborator);
        $this->fetchActivitiesTestResponse($query)->assertOk();

        $this->loginUser($folderOwner);
        $this->fetchActivitiesTestResponse($query)->assertOk();
    }

    #[Test]
    public function whenActivitiesVisibilityIsPrivate(): void
    {
        [$user, $collaborator, $folderOwner] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(new ActivitiesVisibility(Visibility::PRIVATE))
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->loginUser($user);
        $this->fetchActivitiesTestResponse($query = ['folder_id' => $folder->public_id->present()])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'CannotViewFolderActivities']);

        $this->loginUser($collaborator);
        $this->fetchActivitiesTestResponse($query)
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'CannotViewFolderActivities']);

        $this->loginUser($folderOwner);
        $this->fetchActivitiesTestResponse($query)->assertOk();
    }

    #[Test]
    public function whenActivitiesVisibilityIsCollaboratorsOnly(): void
    {
        [$user, $collaborator, $folderOwner] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(new ActivitiesVisibility(Visibility::COLLABORATORS))
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->loginUser($user);
        $this->fetchActivitiesTestResponse($query = ['folder_id' => $folder->public_id->present()])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'CannotViewFolderActivities']);

        $this->loginUser($collaborator);
        $this->fetchActivitiesTestResponse($query)->assertOk();

        $this->loginUser($folderOwner);
        $this->fetchActivitiesTestResponse($query)->assertOk();
    }

    #[Test]
    public function willSortByLatestFirst(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        (new CreateFolderActivity())->create($folder, new FolderIconChangedActivityLogData($user), Type::ICON_CHANGED);
        (new CreateFolderActivity())->create($folder, new FolderNameChangedActivityLogData($user, 'foo', 'bar'), Type::NAME_CHANGED);

        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'FolderNameChangedActivity');
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->fetchActivitiesTestResponse(['folder_id' => $this->generateFolderId()->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    #[Test]
    public function whenFolderVisibilityIsPrivate(): void
    {
        [$folderOwner, $user] = UserFactory::times(2)->create();

        $factory = FolderFactory::new()->for($folderOwner);
        $setting = new ActivitiesVisibility(Visibility::PUBLIC);

        $privateFolder = $factory->private()->create();
        $passwordProtectedFolder = $factory->passwordProtected()->create();
        $privateFolderWithPublicActivitiesVisibility = $factory->private()->settings($setting)->create();
        $passwordProtectedFolderWithPublicActivitiesVisibility = $factory->passwordProtected()->settings($setting)->create();

        $this->loginUser($user);
        $this->fetchActivitiesTestResponse(['folder_id' => $privateFolder->public_id->present()])
            ->assertNotFound()
            ->assertJsonFragment($error = ['message' => 'FolderNotFound']);

        $this->fetchActivitiesTestResponse(['folder_id' => $passwordProtectedFolder->public_id->present()])
            ->assertNotFound()
            ->assertJsonFragment($error);

        $this->fetchActivitiesTestResponse(['folder_id' => $privateFolderWithPublicActivitiesVisibility->public_id->present()])
            ->assertNotFound()
            ->assertJsonFragment($error);

        $this->fetchActivitiesTestResponse(['folder_id' => $passwordProtectedFolderWithPublicActivitiesVisibility->public_id->present()])
            ->assertNotFound()
            ->assertJsonFragment($error);

        $this->loginUser($folderOwner);
        $this->fetchActivitiesTestResponse(['folder_id' => $privateFolder->public_id->present()])->assertOk();
        $this->fetchActivitiesTestResponse(['folder_id' => $passwordProtectedFolder->public_id->present()])->assertOk();
        $this->fetchActivitiesTestResponse(['folder_id' => $privateFolderWithPublicActivitiesVisibility->public_id->present()])->assertOk();
        $this->fetchActivitiesTestResponse(['folder_id' => $passwordProtectedFolderWithPublicActivitiesVisibility->public_id->present()])->assertOk();
    }

    #[Test]
    public function whenFolderVisibilityIsCollaboratorsOnly(): void
    {
        [$folderOwner, $user] = UserFactory::times(2)->create();

        $setting = new ActivitiesVisibility(Visibility::PUBLIC);

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->visibleToCollaboratorsOnly()
            ->settings($setting)
            ->create();

        $this->loginUser($user);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->loginUser($folderOwner);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])->assertOk();
    }
}
