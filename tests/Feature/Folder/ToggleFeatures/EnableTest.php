<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\ToggleFeatures;

use App\Actions\ToggleFolderFeature;
use App\Enums\Feature;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Folder\Concerns\InteractsWithValues;

class EnableTest extends TestCase
{
    use InteractsWithValues;

    protected function shouldBeInteractedWith(): mixed
    {
        return Feature::publicIdentifiers();
    }

    protected function toggleFolderFeatureResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(
            route('enableFolderFeature', $parameters),
        );
    }

    #[Test]
    public function url(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/features/{feature}/enable', 'enableFolderFeature');
    }

    #[Test]
    public function willReturnAuthorizedWhenUserIsNotSignedIn(): void
    {
        $this->toggleFolderFeatureResponse(['folder_id' => 43, 'feature' => 'bar'])->assertUnauthorized();
    }

    #[Test]
    public function willReturnUnprocessableWhenParameterInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->toggleFolderFeatureResponse(['folder_id' => 'foo', 'feature' => 'disable'])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->toggleFolderFeatureResponse(['folder_id' => $this->generateFolderId()->present(), 'feature' => 'bar'])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'InvalidFeatureId']);
    }

    #[Test]
    #[DataProvider('toggleFeatureDataProvider')]
    public function enableFeature(Feature $feature): void
    {
        $user = UserFactory::new()->create();

        $folder = FolderFactory::new()->for($user)->create();

        $toggleFeature = new ToggleFolderFeature();
        $toggleFeature->disable($folder->id, $feature);

        $data = [
            'folder_id' => $folder->public_id->present(),
            'feature' => $feature->present()
        ];

        $this->loginUser($user);
        $this->toggleFolderFeatureResponse($data)->assertCreated();
        $this->assertCount(0, $folder->disabledFeatureTypes);

        //when feature is already enabled
        $this->toggleFolderFeatureResponse($data)->assertNoContent();
        $this->assertCount(0, $folder->disabledFeatureTypes);
    }

    #[Test]
    public function whenFolderAlreadyHasDisabledFeature(): void
    {
        $user = UserFactory::new()->create();
        $folder = FolderFactory::new()->for($user)->create();

        $toggleFeature = new ToggleFolderFeature();

        $toggleFeature->disable($folder->id, Feature::SUSPEND_USER);
        $toggleFeature->disable($folder->id, Feature::ADD_BOOKMARKS);

        $data = [
            'folder_id' => $folder->public_id->present(),
            'feature'  => 'addBookmarks'
        ];

        $this->loginUser($user);
        $this->toggleFolderFeatureResponse($data)->assertCreated();

        $this->assertEquals(
            $folder->disabledFeatureTypes->sole()->name,
            Feature::SUSPEND_USER->value
        );
    }
}
