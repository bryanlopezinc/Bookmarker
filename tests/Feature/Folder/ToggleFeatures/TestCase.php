<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\ToggleFeatures;

use App\Enums\Feature;
use App\Enums\Permission;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Exception;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Traits\GeneratesId;
use Tests\TestCase as BaseTestCase;
use Tests\Traits\CreatesCollaboration;

class TestCase extends BaseTestCase
{
    use GeneratesId;
    use CreatesCollaboration;

    protected function toggleFolderFeatureResponse(array $data): TestResponse
    {
        throw new Exception(__FUNCTION__ . ' not implemented');
    }

    final public static function toggleFeatureDataProvider(): array
    {
        $data = [];

        foreach (Feature::cases() as $feature) {
            $name = str($feature->value)->lower()->camel()->toString();

            $data[$name] = [$feature];
        }

        return $data;
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        $folder = FolderFactory::new()->create();

        $this->loginUser();
        $this->toggleFolderFeatureResponse(['folder_id' => $folder->public_id->present(), 'feature' => 'addBookmarks'])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->assertTrue($folder->disabledFeatureTypes->isEmpty());
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $this->loginUser();
        $this->toggleFolderFeatureResponse(['folder_id' => $this->generateFolderId()->present(), 'feature' => 'addBookmarks'])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    #[Test]
    public function willReturnForbiddenWhenCollaboratorIsTogglingFeature(): void
    {
        $collaborator = UserFactory::new()->create();

        $folder = FolderFactory::new()->create();

        $this->CreateCollaborationRecord($collaborator, $folder, array_column(Permission::cases(), 'value'));

        $this->loginUser($collaborator);
        $this->toggleFolderFeatureResponse(['feature' => 'suspendUser', 'folder_id' => $folder->public_id->present()])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'PermissionDenied']);

        $this->assertTrue($folder->disabledFeatureTypes->isEmpty());
    }
}
