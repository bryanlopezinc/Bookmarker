<?php

namespace Tests\Feature\Folder;

use App\Models\Taggable;
use Database\Factories\FolderFactory;
use Database\Factories\TagFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DeleteFolderTagsTest extends TestCase
{
    use WithFaker;

    protected function deleteFolderTagsResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('deleteFolderTags'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/folders/tags/remove', 'deleteFolderTags');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->deleteFolderTagsResponse()->assertUnauthorized();
    }

    public function testRequiredAttrbutesMustBePresent(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->deleteFolderTagsResponse()->assertJsonValidationErrors(['id', 'tags']);
    }

    public function testWillDeleteFolderTags(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $model = FolderFactory::new()->create(['user_id' => $user->id]);
        $tag = TagFactory::new()->create(['created_by' => $user->id]);

        Taggable::create($tagAttributes = [
            'taggable_id' => $model->id,
            'tag_id' => $tag->id,
            'taggable_type' => Taggable::FOLDER_TYPE
        ]);

        $this->deleteFolderTagsResponse([
            'id' => $model->id,
            'tags' => $tag->name
        ])->assertOk();

        $this->assertDatabaseMissing(Taggable::class, $tagAttributes);
    }

    public function testFolderMustExist(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $model = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->deleteFolderTagsResponse(['id' => $model->id + 1, 'tags' => $this->faker->word])->assertNotFound();
    }

    public function testWillReturnSuccessIfFolderDoesNotHaveTags(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $model = FolderFactory::new()->create(['user_id' => $user->id]);

        $this->deleteFolderTagsResponse([
            'id' => $model->id,
            'tags' => $this->faker->word
        ])->assertOk();
    }

    public function testFolderMustBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $model = FolderFactory::new()->create();

        $this->deleteFolderTagsResponse([
            'id' => $model->id,
            'tags' => $this->faker->word
        ])->assertForbidden();
    }
}