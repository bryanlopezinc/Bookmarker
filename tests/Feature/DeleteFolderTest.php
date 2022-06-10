<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\UserFoldersCount;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DeleteFolderTest extends TestCase
{
    use WithFaker;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('deleteFolder'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/folders', 'createFolder');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationWhenRequiredAttrbutesAreMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse()->assertJsonValidationErrors(['folder']);
    }

    public function testDeleteFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folderIDs = FolderFactory::new()->count(3)->create([
            'user_id' => $user->id
        ])->pluck('id');

        UserFoldersCount::create([
            'count' => 3,
            'user_id' => $user->id
        ]);

        $this->getTestResponse(['folder' => $folderIDs->last()])->assertOk();

        $folderIDs->take(2)->each(function (int $folderID) {
            $this->assertDatabaseHas(Folder::class, ['id' => $folderID]);
        });

        $this->assertDatabaseMissing(Folder::class, ['id' => $folderIDs->last()]);

        $this->assertDatabaseHas(UserFoldersCount::class, [
            'user_id' => $user->id,
            'count' => 2,
            'type' => UserFoldersCount::TYPE
        ]);
    }

    public function testCanOnlyDeleteOwnFolder(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $folderID = FolderFactory::new()->create()->id;

        $this->getTestResponse(['folder' => $folderID])->assertForbidden();
    }

    public function testCannotDeleteInvalidFolder(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $folderID = FolderFactory::new()->create()->id;

        $this->getTestResponse(['folder' => $folderID + 1])
            ->assertNotFound()
            ->assertExactJson([
                'message' => "The folder does not exists"
            ]);;
    }
}
