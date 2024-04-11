<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Enums\Feature;
use App\Models\FolderDisabledFeature;
use App\Models\FolderFeature;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Folder\Concerns\InteractsWithValues;

class ToggleFolderFeatureTest extends TestCase
{
    use InteractsWithValues;

    protected function shouldBeInteractedWith(): mixed
    {
        return Feature::publicIdentifiers();
    }

    public function updateFolderResponse(array $parameters = []): TestResponse
    {
        return $this->patchJson(
            route('updateFolderCollaboratorActions', ['folder_id' => $parameters['folder_id']]),
            $parameters
        );
    }

    #[Test]
    public function url(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/features', 'updateFolderCollaboratorActions');
    }

    #[Test]
    public function willReturnAuthorizedWhenUserIsNotSignedIn(): void
    {
        $this->updateFolderResponse(['folder_id' => 43])->assertUnauthorized();
    }

    #[Test]
    public function willReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->updateFolderResponse(['folder_id' => 'foo'])->assertNotFound();

        //assert at least one parameter must be present
        $this->updateFolderResponse(['folder_id' => 2])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['addBookmarks']);

        $this->updateFolderResponse(['addBookmarks' => 'foo', 'folder_id' => 42])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['addBookmarks']);

        $this->updateFolderResponse(['inviteUsers' => 'foo', 'folder_id' => 1902])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['inviteUsers']);

        $this->updateFolderResponse(['updateFolder' => 'foo', 'folder_id' => 21])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['updateFolder']);
    }

    #[Test]
    public function toggleAddBookmarks(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->toggleFeature(
            folderId: $folder->id,
            id: null,
            assertions: [
                'Will disable feature' => [
                    'data' => ['addBookmarks' => 'disable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(1, $disabledFeatures);
                        $this->assertEquals($disabledFeatures->first()->name, 'ADD_BOOKMARKS');
                    }
                ],

                'Will return ok when feature is already disabled' => [
                    'data' => ['addBookmarks' => 'disable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(1, $disabledFeatures);
                        $this->assertEquals($disabledFeatures->first()->name, 'ADD_BOOKMARKS');
                    }
                ],

                'Will re-enable feature' => [
                    'data' => ['addBookmarks' => 'enable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(0, $disabledFeatures);
                    }
                ],
            ]
        );
    }

    protected function toggleFeature(array $assertions, int $folderId, string $id = null)
    {
        if ($id) {
            $assertions = Arr::only($assertions, $id);
            $this->assertNotEmpty($assertions);
        }

        foreach ($assertions as $test) {
            $response = $this->updateFolderResponse(array_merge(['folder_id' => $folderId], $test['data']));
            $response->assertOk();

            $disabledFeaturesIds = FolderDisabledFeature::where('folder_id', $folderId)->get(['feature_id'])->pluck('feature_id');

            $disabledFeatures = FolderFeature::query()->whereKey($disabledFeaturesIds)->get(['name']);

            foreach (Arr::wrap($test['expectation']) as $expectation) {
                $expectation($disabledFeatures, $response);
            }
        }

        if ($id) {
            $this->markTestIncomplete('Some toggle features assertions were not made');
        }
    }

    #[Test]
    public function toggleRemoveBookmarks(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->toggleFeature(
            folderId: $folder->id,
            id: null,
            assertions: [
                'Will disable feature' => [
                    'data' => ['removeBookmarks' => 'disable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(1, $disabledFeatures);
                        $this->assertEquals($disabledFeatures->first()->name, 'DELETE_BOOKMARKS');
                    }
                ],

                'Will return ok when restriction is already disabled' => [
                    'data' => ['removeBookmarks' => 'disable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(1, $disabledFeatures);
                        $this->assertEquals($disabledFeatures->first()->name, 'DELETE_BOOKMARKS');
                    }
                ],

                'Will re-enable feature' => [
                    'data' => ['removeBookmarks' => 'enable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(0, $disabledFeatures);
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

        $this->toggleFeature(
            folderId: $folder->id,
            id: null,
            assertions: [
                'Will disable feature' => [
                    'data' => ['inviteUsers' => 'disable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(1, $disabledFeatures);
                        $this->assertEquals($disabledFeatures->first()->name, 'SEND_INVITES');
                    }
                ],

                'Will return ok when restriction is already disabled' => [
                    'data' => ['inviteUsers' => 'disable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(1, $disabledFeatures);
                        $this->assertEquals($disabledFeatures->first()->name, 'SEND_INVITES');
                    }
                ],

                'Will re-enable feature' => [
                    'data' => ['inviteUsers' => 'enable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(0, $disabledFeatures);
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

        $this->toggleFeature(
            folderId: $folder->id,
            id: null,
            assertions: [
                'disable feature' => [
                    'data' => ['updateFolder' => 'disable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(1, $disabledFeatures);
                        $this->assertEquals($disabledFeatures->first()->name, 'UPDATE_FOLDER');
                    }
                ],

                'Will return ok when feature is already disabled' => [
                    'data' => ['updateFolder' => 'disable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(1, $disabledFeatures);
                        $this->assertEquals($disabledFeatures->first()->name, 'UPDATE_FOLDER');
                    }
                ],

                'Will re-enable feature' => [
                    'data' => ['updateFolder' => 'enable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(0, $disabledFeatures);
                    }
                ],
            ]
        );
    }

    #[Test]
    public function toggleRemoveUser(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->toggleFeature(
            folderId: $folder->id,
            id: null,
            assertions: [
                'disable feature' => [
                    'data' => ['removeUser' => 'disable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(1, $disabledFeatures);
                        $this->assertEquals($disabledFeatures->first()->name, 'REMOVE_USER');
                    }
                ],

                'Will return ok when feature is already disabled' => [
                    'data' => ['removeUser' => 'disable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(1, $disabledFeatures);
                        $this->assertEquals($disabledFeatures->first()->name, 'REMOVE_USER');
                    }
                ],

                'Will re-enable feature' => [
                    'data' => ['removeUser' => 'enable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(0, $disabledFeatures);
                    }
                ],
            ]
        );
    }

    #[Test]
    public function toggleUpdateFolderName(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->toggleFeature(
            folderId: $folder->id,
            id: null,
            assertions: [
                'disable feature' => [
                    'data' => ['updateFolderName' => 'disable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(1, $disabledFeatures);
                        $this->assertEquals($disabledFeatures->first()->name, 'UPDATE_FOLDER_NAME');
                    }
                ],

                'Will return ok when feature is already disabled' => [
                    'data' => ['updateFolderName' => 'disable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(1, $disabledFeatures);
                        $this->assertEquals($disabledFeatures->first()->name, 'UPDATE_FOLDER_NAME');
                    }
                ],

                'Will re-enable feature' => [
                    'data' => ['updateFolderName' => 'enable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(0, $disabledFeatures);
                    }
                ],
            ]
        );
    }

    #[Test]
    public function toggleUpdateFolderDescription(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->toggleFeature(
            folderId: $folder->id,
            id: null,
            assertions: [
                'disable feature' => [
                    'data' => ['updateFolderDescription' => 'disable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(1, $disabledFeatures);
                        $this->assertEquals($disabledFeatures->first()->name, 'UPDATE_FOLDER_DESCRIPTION');
                    }
                ],

                'Will return ok when feature is already disabled' => [
                    'data' => ['updateFolderDescription' => 'disable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(1, $disabledFeatures);
                        $this->assertEquals($disabledFeatures->first()->name, 'UPDATE_FOLDER_DESCRIPTION');
                    }
                ],

                'Will re-enable feature' => [
                    'data' => ['updateFolderDescription' => 'enable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(0, $disabledFeatures);
                    }
                ],
            ]
        );
    }

    #[Test]
    public function toggleJoinFolder(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->toggleFeature(
            folderId: $folder->id,
            id: null,
            assertions: [
                'disable feature' => [
                    'data' => ['joinFolder' => 'disable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(1, $disabledFeatures);
                        $this->assertEquals($disabledFeatures->first()->name, 'JOIN_FOLDER');
                    }
                ],

                'Will return ok when feature is already disabled' => [
                    'data' => ['joinFolder' => 'disable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(1, $disabledFeatures);
                        $this->assertEquals($disabledFeatures->first()->name, 'JOIN_FOLDER');
                    }
                ],

                'Will re-enable feature' => [
                    'data' => ['joinFolder' => 'enable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(0, $disabledFeatures);
                    }
                ],
            ]
        );
    }

    #[Test]
    public function toggleUpdateFolderIcon(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->toggleFeature(
            folderId: $folder->id,
            id: null,
            assertions: [
                'disable feature' => [
                    'data' => ['updateFolderIcon' => 'disable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(1, $disabledFeatures);
                        $this->assertEquals($disabledFeatures->first()->name, 'UPDATE_FOLDER_ICON');
                    }
                ],

                'Will return ok when feature is already disabled' => [
                    'data' => ['updateFolderIcon' => 'disable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(1, $disabledFeatures);
                        $this->assertEquals($disabledFeatures->first()->name, 'UPDATE_FOLDER_ICON');
                    }
                ],

                'Will re-enable feature' => [
                    'data' => ['updateFolderIcon' => 'enable'],
                    'expectation' => function (Collection $disabledFeatures) {
                        $this->assertCount(0, $disabledFeatures);
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

        $this->updateFolderResponse(['folder_id' => $folderId, 'addBookmarks' => 'disable'])->assertNotFound();

        $this->assertDatabaseMissing(FolderDisabledFeature::class, ['folder_id' => $folderId]);
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $folder = FolderFactory::new()->create();

        $this->updateFolderResponse(['folder_id' => $folder->id + 1, 'addBookmarks' => 'disable'])->assertNotFound();

        $this->assertDatabaseMissing(FolderDisabledFeature::class, ['folder_id' => $folder->id + 1]);
    }
}
