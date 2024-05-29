<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\ToggleFeatures;

use App\Enums\Feature;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Folder\Concerns\InteractsWithValues;

class DisableTest extends TestCase
{
    use InteractsWithValues;

    protected function shouldBeInteractedWith(): mixed
    {
        return Feature::publicIdentifiers();
    }

    protected function toggleFolderFeatureResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(
            route('disableFolderFeature', $parameters),
        );
    }

    #[Test]
    public function url(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/features/{feature}/disable', 'disableFolderFeature');
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
    public function disableFeature(Feature $feature): void
    {
        $user = UserFactory::new()->create();

        $folder = FolderFactory::new()->for($user)->create();

        $data = [
            'folder_id' => $folder->public_id->present(),
            'feature'  => $feature->present()
        ];

        $this->loginUser($user);
        $this->toggleFolderFeatureResponse($data)->assertOk();
        $this->assertEquals($feature->name, $folder->disabledFeatureTypes->sole()->name);

        //when feature is already disabled
        $this->toggleFolderFeatureResponse($data)->assertNoContent();
        $this->assertEquals($feature->name, $folder->disabledFeatureTypes->sole()->name);
    }

    #[Test]
    public function whenFolderAlreadyHasDisabledFeature(): void
    {
        $user = UserFactory::new()->create();

        $folder = FolderFactory::new()->for($user)->create();

        $data = [
            'folder_id' => $folder->public_id->present(),
            'feature'  => 'addBookmarks'
        ];

        $this->loginUser($user);
        $this->toggleFolderFeatureResponse($data)->assertOk();
        $this->toggleFolderFeatureResponse([...$data, 'feature' => 'suspendUser',])->assertOk();

        $this->assertCount(2, $folder->disabledFeatureTypes);
        $this->assertEqualsCanonicalizing(
            $folder->disabledFeatureTypes->pluck('name')->all(),
            [Feature::SUSPEND_USER->value, Feature::ADD_BOOKMARKS->value]
        );
    }
}
