<?php

namespace Tests\Feature\Folder;

use App\Enums\FolderSettingKey;
use App\Enums\Permission;
use App\Models\Folder;
use App\Models\FolderBookmark;
use App\Models\FolderSetting;
use App\Services\Folder\AddBookmarksToFolderService;
use App\Services\Folder\ToggleFolderCollaborationRestriction;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderCollaboratorPermissionFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RemoveBookmarksFromFolderTest extends TestCase
{
    use WithFaker;

    protected function removeFolderBookmarksResponse(array $parameters = []): TestResponse
    {
        if (array_key_exists('bookmarks', $parameters)) {
            $parameters['bookmarks'] = implode(',', Arr::wrap($parameters['bookmarks']));
        }

        return $this->deleteJson(route('removeBookmarksFromFolder'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/bookmarks', 'removeBookmarksFromFolder');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->removeFolderBookmarksResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationWhenRequiredAttributesAreMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->removeFolderBookmarksResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bookmarks', 'folder']);
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->removeFolderBookmarksResponse(['bookmarks' => ['1', '2bar']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                "folder"      => ["The folder field is required."],
                "bookmarks.1" => ["The bookmarks.1 attribute is invalid"]
            ]);

        $this->removeFolderBookmarksResponse(['bookmarks' => ['1', '3', '4', '1']])
            ->assertJsonValidationErrors([
                "bookmarks.0" => ["The bookmarks.0 field has a duplicate value."],
                "bookmarks.3" => ["The bookmarks.3 field has a duplicate value."]
            ]);

        $this->removeFolderBookmarksResponse(['bookmarks' => range(1, 51)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'bookmarks' => 'The bookmarks must not have more than 50 items.'
            ]);
    }

    public function testRemoveBookmarks(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarkIDs = BookmarkFactory::new()->count(2)->for($user)->create([
            'created_at' => $createdAt = now()->yesterday(),
            'updated_at' => $createdAt,
        ])->pluck('id');

        $folderID = FolderFactory::new()->for($user)->create()->id;

        $this->addBookmarksToFolder($bookmarkIDs->all(), $folderID);

        $this->removeFolderBookmarksResponse([
            'bookmarks' => [$bookmarkIDs[0]],
            'folder'    => $folderID
        ])->assertSuccessful();

        $this->assertDatabaseMissing(FolderBookmark::class, [
            'bookmark_id' => $bookmarkIDs[0],
            'folder_id'   => $folderID
        ]);

        $this->assertDatabaseHas(FolderBookmark::class, [
            'bookmark_id' => [$bookmarkIDs[1]],
            'folder_id'   => $folderID
        ]);

        //Assert the folder updated_at column was updated
        $this->assertTrue(
            Folder::query()->whereKey($folderID)->first('updated_at')->updated_at->isToday()
        );
    }

    public function testWillReturnNotFoundResponseWhenBookmarksDoesNotExistsInFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarkIDs = BookmarkFactory::new()->count(2)->for($user)->create()->pluck('id');
        $folderID = FolderFactory::new()->for($user)->create()->id;

        //Assert will return not found when all bookmarks don't exist in folder
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarkIDs->all(),
            'folder'    => $folderID
        ])->assertNotFound()
            ->assertExactJson($error = ['message' => "BookmarkNotFound"]);

        $this->addBookmarksToFolder($bookmarkIDs[0], $folderID);

        //Assert will return not found when some (but not all) bookmarks exist in folder
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarkIDs->all(),
            'folder'    => $folderID
        ])->assertNotFound()
            ->assertExactJson($error);
    }

    public function testWillReturnNotFoundWhenBookmarkHasBeenDeleted(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::times(2)->for($user)->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolder($bookmarks->pluck('id')->all(), $folder->id);

        $bookmarks->first()->delete();

        $this->removeFolderBookmarksResponse([
            'folder'    => $folder->id,
            'bookmarks' => $bookmarks->pluck('id')->all()
        ])->assertNotFound()
            ->assertExactJson(['message' => 'BookmarkNotFound']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(2)->for($user)->create();

        $folder = FolderFactory::new()->for(UserFactory::new())->create();

        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarks->pluck('id')->all(),
            'folder'    => $folder->id
        ])->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }

    public function testUserWithPermissionCanRemoveBookmarksFromFolder(): void
    {
        [$folderOwner, $user] = UserFactory::new()->count(2)->create();

        $bookmarkIDs = BookmarkFactory::times(3)->for($folderOwner)->create()->pluck('id');
        $folderID = FolderFactory::new()->for($folderOwner)->create()->id;

        Passport::actingAs($folderOwner);

        $this->addBookmarksToFolder($bookmarkIDs->all(), $folderID);

        FolderCollaboratorPermissionFactory::new()
            ->user($user->id)
            ->folder($folderID)
            ->removeBookmarksPermission()
            ->create();

        Passport::actingAs($user);

        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarkIDs->all(),
            'folder' => $folderID
        ])->assertOk();
    }

    public function testWillReturnForbiddenWhenCollaboratorDoesNotHaveRemoveBookmarksPermission(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $bookmarkIDs = BookmarkFactory::times(3)->for($folderOwner)->create()->pluck('id');
        $folderID = FolderFactory::new()->for($folderOwner)->create()->id;
        $folderAccessFactory = FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folderID);

        $folderAccessFactory->updateFolderPermission()->create();
        $folderAccessFactory->addBookmarksPermission()->create();
        $folderAccessFactory->viewBookmarksPermission()->create();
        $folderAccessFactory->inviteUser()->create();

        Passport::actingAs($folderOwner);
        $this->addBookmarksToFolder($bookmarkIDs->all(), $folderID);

        Passport::actingAs($collaborator);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarkIDs->all(),
            'folder'    => $folderID
        ])->assertForbidden()
            ->assertExactJson(['message' => 'NoRemoveBookmarksPermission']);
    }

    private function addBookmarksToFolder(int|array $bookmarkIDs, int $folderID): void
    {
        /** @var AddBookmarksToFolderService */
        $service = app(AddBookmarksToFolderService::class);

        $service->add($folderID, $bookmarkIDs);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(3)->for($user)->create();
        $folder = FolderFactory::new()->for($user)->create();

        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarks->pluck('id')->all(),
            'folder'    => $folder->id + 1
        ])->assertNotFound()
            ->assertExactJson(['message' => "FolderNotFound"]);
    }

    public function test_user_with_permission_cannot_remove_bookmarks_when_folder_owner_has_deleted_account(): void
    {
        [$collaborator, $folderOwner] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        FolderCollaboratorPermissionFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->removeBookmarksPermission()
            ->create();

        $folderOwner->delete();

        Passport::actingAs($collaborator);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => [1, 2],
            'folder'    => $folder->id,
        ])->assertNotFound()
            ->assertExactJson(['message' => "FolderNotFound"]);
    }

    public function testWillNotSendNotificationWhenBookmarksWereRemovedByFolderOwner(): void
    {
        $folderOwner = UserFactory::new()->create();
        $bookmarkIDs = BookmarkFactory::times(3)->for($folderOwner)->create()->pluck('id');
        $folderID = FolderFactory::new()->create(['user_id' => $folderOwner->id])->id;

        Notification::fake();

        Passport::actingAs($folderOwner);
        $this->addBookmarksToFolder($bookmarkIDs->all(), $folderID);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarkIDs->all(),
            'folder'    => $folderID
        ])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillSendNotificationsWhenBookmarksWereNotRemovedByFolderOwner(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $bookmarkIDs = BookmarkFactory::times(3)->for($folderOwner)->create()->pluck('id');
        $folderID = FolderFactory::new()->for($folderOwner)->create()->id;

        Passport::actingAs($folderOwner);
        $this->addBookmarksToFolder($bookmarkIDs->all(), $folderID);

        FolderCollaboratorPermissionFactory::new()
            ->user($collaborator->id)
            ->folder($folderID)
            ->removeBookmarksPermission()
            ->create();

        Passport::actingAs($collaborator);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarkIDs->all(),
            'folder' => $folderID
        ])->assertOk();

        $notificationData = DatabaseNotification::query()->where('notifiable_id', $folderOwner->id)->sole(['data'])->data;

        $this->assertEquals($folderID, $notificationData['removed_from_folder']);
        $this->assertEquals($collaborator->id, $notificationData['removed_by']);

        foreach ($notificationData['bookmarks_removed'] as $bookmarkID) {
            $this->assertTrue($bookmarkIDs->contains($bookmarkID));
        }
    }

    public function testWillNotSendNotificationsWhenNotificationsIsDisabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $bookmarkIDs = BookmarkFactory::times(3)->for($folderOwner)->create()->pluck('id');
        $folderID = FolderFactory::new()->for($folderOwner)->create()->id;

        FolderSetting::create([
            'key'       => FolderSettingKey::ENABLE_NOTIFICATIONS->value,
            'value'     => false,
            'folder_id' => $folderID
        ]);

        Passport::actingAs($folderOwner);
        $this->addBookmarksToFolder($bookmarkIDs->all(), $folderID);

        FolderCollaboratorPermissionFactory::new()
            ->user($collaborator->id)
            ->folder($folderID)
            ->removeBookmarksPermission()
            ->create();

        Notification::fake();

        Passport::actingAs($collaborator);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarkIDs->all(),
            'folder'    => $folderID
        ])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotSendNotificationsWhenBookmarksRemovedNotificationsIsDisabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $bookmarkIDs = BookmarkFactory::times(3)->for($folderOwner)->create()->pluck('id');
        $folderID = FolderFactory::new()->for($folderOwner)->create()->id;

        FolderSetting::create([
            'key'       => FolderSettingKey::NOTIFY_ON_BOOKMARK_DELETED->value,
            'value'     => false,
            'folder_id' => $folderID
        ]);

        Passport::actingAs($folderOwner);
        $this->addBookmarksToFolder($bookmarkIDs->all(), $folderID);

        FolderCollaboratorPermissionFactory::new()
            ->user($collaborator->id)
            ->folder($folderID)
            ->removeBookmarksPermission()
            ->create();

        Notification::fake();

        Passport::actingAs($collaborator);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarkIDs->all(),
            'folder'    => $folderID
        ])->assertOk();

        Notification::assertNothingSent();
    }

    #[Test]
    public function willReturnCorrectResponseWhenActionsIsDisabled(): void
    {
        /** @var ToggleFolderCollaborationRestriction */
        $updateCollaboratorActionService = app(ToggleFolderCollaborationRestriction::class);

        /** @var AddBookmarksToFolderService */
        $addBooksService = app(AddBookmarksToFolderService::class);

        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $bookmarks = BookmarkFactory::times(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $addBooksService->add($folder->id, $bookmarks->pluck('id')->all());

        FolderCollaboratorPermissionFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->removeBookmarksPermission()
            ->create();

        //Assert collaborator can remove bookmark when disabled action is not remove bookmark action
        $updateCollaboratorActionService->update($folder->id, Permission::INVITE_USER, false);
        $this->loginUser($collaborator);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarks[0]->id,
            'folder'    => $folder->id
        ])->assertOk();

        $updateCollaboratorActionService->update($folder->id, Permission::DELETE_BOOKMARKS, false);

        $this->removeFolderBookmarksResponse($query = ['bookmarks' => $bookmarks[1]->id, 'folder' => $folder->id])
            ->assertForbidden()
            ->assertExactJson(['message' => 'RemoveBookmarksActionDisabled']);

        //when user is not a collaborator
        $this->loginUser(UserFactory::new()->create());
        $this->removeFolderBookmarksResponse($query)->assertNotFound();

        $this->loginUser($folderOwner);
        $this->removeFolderBookmarksResponse($query)->assertOk();
    }
}
