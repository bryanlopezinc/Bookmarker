<?php

namespace Tests\Feature\Folder;

use App\Models\FolderDisabledFeature;
use App\Repositories\Folder\FeaturesRepository;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ToggleFolderFeatureTest extends TestCase
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

    protected function toggleRestriction(array $assertions, int $folderId, string $id = null)
    {
        $featuresRepository = new FeaturesRepository();

        if ($id) {
            $assertions = Arr::only($assertions, $id);
        }

        foreach ($assertions as $test) {
            $response = $this->updateFolderResponse(array_merge(['folder_id' => $folderId], $test['data']));
            $response->assertOk();

            $disabledFeaturesIds = FolderDisabledFeature::where('folder_id', $folderId)->get(['feature_id'])->pluck('feature_id');

            $disabledFeatures = $featuresRepository->findManyById($disabledFeaturesIds);

            foreach (Arr::wrap($test['expectation']) as $expectation) {
                $expectation($disabledFeatures, $response);
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

        $this->toggleRestriction(
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

        $this->toggleRestriction(
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
