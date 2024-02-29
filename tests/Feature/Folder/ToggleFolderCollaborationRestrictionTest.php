<?php

namespace Tests\Feature\Folder;

use App\Models\FolderDisabledFeature;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ToggleFolderCollaborationRestrictionTest extends TestCase
{
    public function updateFolderResponse(array $parameters = []): TestResponse
    {
        return $this->patchJson(
            route('updateFolderCollaboratorActions'),
            $parameters
        );
    }

    #[Test]
    public function url(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/collaborators/actions', 'updateFolderCollaboratorActions');
    }

    #[Test]
    public function willReturnAuthorizedWhenUserIsNotSignedIn(): void
    {
        $this->updateFolderResponse()->assertUnauthorized();
    }

    #[Test]
    public function willReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->updateFolderResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['folder_id']);

        //assert at least one parameter must be present
        $this->updateFolderResponse(['folder_id' => 2])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['addBookmarks']);

        $this->updateFolderResponse(['addBookmarks' => 'foo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['addBookmarks']);

        $this->updateFolderResponse(['inviteUsers' => 'foo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['inviteUsers']);

        $this->updateFolderResponse(['updateFolder' => 'foo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['updateFolder']);
    }

    #[Test]
    public function toggleAddBookmarks(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->toggleRestriction(
            folderId: $folder->id,
            id: null,
            assertions: [
                'Will return ok when restriction is already enabled' => [
                    'data' => ['addBookmarks' => true],
                    'expectation' => function (Collection $disabledActions) {
                        $this->assertCount(0, $disabledActions);
                    }
                ],

                'Will enable restrictions' => [
                    'data' => ['addBookmarks' => false],
                    'expectation' => function (Collection $disabledActions) {
                        $this->assertCount(1, $disabledActions);
                        $this->assertEquals($disabledActions->first()->feature, 'ADD_BOOKMARKS');
                    }
                ],

                'Will return ok when restriction is already enabled' => [
                    'data' => ['addBookmarks' => false],
                    'expectation' => function (Collection $disabledActions) {
                        $this->assertCount(1, $disabledActions);
                        $this->assertEquals($disabledActions->first()->feature, 'ADD_BOOKMARKS');
                    }
                ],

                'Will disable restriction' => [
                    'data' => ['addBookmarks' => true],
                    'expectation' => function (Collection $disabledActions) {
                        $this->assertCount(0, $disabledActions);
                    }
                ],
            ]
        );
    }

    protected function toggleRestriction(array $assertions, int $folderId, string $id = null)
    {
        if ($id) {
            $assertions = Arr::only($assertions, $id);
        }

        foreach ($assertions as $test) {
            $response = $this->updateFolderResponse(array_merge(['folder_id' => $folderId], $test['data']));
            $response->assertOk();

            $disabledFeature = FolderDisabledFeature::where('folder_id', $folderId)->get();

            foreach (Arr::wrap($test['expectation']) as $expectation) {
                $expectation($disabledFeature, $response);
            }
        }

        if ($id) {
            $this->markTestIncomplete('Some toggle bookmarks assertions were not made');
        }
    }

    #[Test]
    public function toggleRemoveBookmarks(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->toggleRestriction(
            folderId: $folder->id,
            id: null,
            assertions: [
                'Will return ok when restriction is already enabled' => [
                    'data' => ['removeBookmarks' => true],
                    'expectation' => function (Collection $disabledActions) {
                        $this->assertCount(0, $disabledActions);
                    }
                ],

                'Will enable restrictions' => [
                    'data' => ['removeBookmarks' => false],
                    'expectation' => function (Collection $disabledActions) {
                        $this->assertCount(1, $disabledActions);
                        $this->assertEquals($disabledActions->first()->feature, 'DELETE_BOOKMARKS');
                    }
                ],

                'Will return ok when restriction is already enabled' => [
                    'data' => ['removeBookmarks' => false],
                    'expectation' => function (Collection $disabledActions) {
                        $this->assertCount(1, $disabledActions);
                        $this->assertEquals($disabledActions->first()->feature, 'DELETE_BOOKMARKS');
                    }
                ],

                'Will disable restriction' => [
                    'data' => ['removeBookmarks' => true],
                    'expectation' => function (Collection $disabledActions) {
                        $this->assertCount(0, $disabledActions);
                    }
                ],
            ]
        );
    }

    #[Test]
    public function toggleInviteUser(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->toggleRestriction(
            folderId: $folder->id,
            id: null,
            assertions: [
                'Will return ok when restriction is already enabled' => [
                    'data' => ['inviteUsers' => true],
                    'expectation' => function (Collection $disabledActions) {
                        $this->assertCount(0, $disabledActions);
                    }
                ],

                'Will enable restrictions' => [
                    'data' => ['inviteUsers' => false],
                    'expectation' => function (Collection $disabledActions) {
                        $this->assertCount(1, $disabledActions);
                        $this->assertEquals($disabledActions->first()->feature, 'INVITE_USER');
                    }
                ],

                'Will return ok when restriction is already enabled' => [
                    'data' => ['inviteUsers' => false],
                    'expectation' => function (Collection $disabledActions) {
                        $this->assertCount(1, $disabledActions);
                        $this->assertEquals($disabledActions->first()->feature, 'INVITE_USER');
                    }
                ],

                'Will disable restriction' => [
                    'data' => ['inviteUsers' => true],
                    'expectation' => function (Collection $disabledActions) {
                        $this->assertCount(0, $disabledActions);
                    }
                ],
            ]
        );
    }

    #[Test]
    public function toggleUpdateFolder(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->toggleRestriction(
            folderId: $folder->id,
            id: null,
            assertions: [
                'Will return ok when restriction is already enabled' => [
                    'data' => ['updateFolder' => true],
                    'expectation' => function (Collection $disabledActions) {
                        $this->assertCount(0, $disabledActions);
                    }
                ],

                'Will enable restrictions' => [
                    'data' => ['updateFolder' => false],
                    'expectation' => function (Collection $disabledActions) {
                        $this->assertCount(1, $disabledActions);
                        $this->assertEquals($disabledActions->first()->feature, 'UPDATE_FOLDER');
                    }
                ],

                'Will return ok when restriction is already enabled' => [
                    'data' => ['updateFolder' => false],
                    'expectation' => function (Collection $disabledActions) {
                        $this->assertCount(1, $disabledActions);
                        $this->assertEquals($disabledActions->first()->feature, 'UPDATE_FOLDER');
                    }
                ],

                'Will disable restriction' => [
                    'data' => ['updateFolder' => true],
                    'expectation' => function (Collection $disabledActions) {
                        $this->assertCount(0, $disabledActions);
                    }
                ],
            ]
        );
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $folderId = FolderFactory::new()->create()->id;

        $this->updateFolderResponse(['folder_id' => $folderId, 'addBookmarks' => false])->assertNotFound();

        $this->assertDatabaseMissing(FolderDisabledFeature::class, ['folder_id' => $folderId]);
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $folder = FolderFactory::new()->create();

        $this->updateFolderResponse(['folder_id' => $folder->id + 1, 'addBookmarks' => false])->assertNotFound();

        $this->assertDatabaseMissing(FolderDisabledFeature::class, ['folder_id' => $folder->id + 1]);
    }
}
