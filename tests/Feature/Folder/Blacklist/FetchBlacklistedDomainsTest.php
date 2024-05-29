<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Blacklist;

use App\Actions\BlacklistDomain;
use App\ValueObjects\Url;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\AssertValidPaginationData;
use Tests\Feature\Folder\Concerns\AssertFolderCollaboratorMetrics;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;
use Tests\Traits\CreatesRole;
use Tests\Traits\GeneratesId;

class FetchBlacklistedDomainsTest extends TestCase
{
    use GeneratesId;
    use CreatesCollaboration;
    use CreatesRole;
    use AssertFolderCollaboratorMetrics;
    use WithFaker;
    use AssertValidPaginationData;

    private function fetchBlacklistDomainsResponse(array $data = []): TestResponse
    {
        return $this->getJson(
            route('fetchBlacklistedDomains', Arr::only($data, ['folder_id'])),
            Arr::except($data, ['folder_id'])
        );
    }

    #[Test]
    public function route(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/bookmarks/domains/blacklist', 'fetchBlacklistedDomains');
    }

    #[Test]
    public function willReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->fetchBlacklistDomainsResponse(['folder_id' => 5])->assertUnauthorized();
    }

    #[Test]
    public function willReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser();

        $this->fetchBlacklistDomainsResponse(['folder_id' => 'foo'])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->assertValidPaginationData($this, 'fetchBlacklistedDomains', ['folder_id' => $this->generateFolderId()->present()]);
    }

    #[Test]
    public function fetch(): void
    {
        $blacklistDomain = new BlacklistDomain();

        $folderOwner = UserFactory::new()->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $blacklistedDomain = $blacklistDomain->create($folder, $folderOwner, new Url('https://forbidden-url.com'));

        $this->loginUser($folderOwner);
        $this->fetchBlacklistDomainsResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(5, 'data.0.attributes')
            ->assertJsonCount(3, 'data.0.attributes.collaborator')
            ->assertJsonPath('data.0.type', 'BlacklistedDomain')
            ->assertJsonPath('data.0.attributes.id', $blacklistedDomain->public_id->present())
            ->assertJsonPath('data.0.attributes.domain', 'forbidden-url.com')
            ->assertJsonPath('data.0.attributes.collaborator_exists', true)
            ->assertJsonPath('data.0.attributes.collaborator.id', $folderOwner->public_id->present())
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'type',
                        'attributes' => [
                            'id',
                            'domain',
                            'blacklisted_at',
                            'collaborator_exists',
                            'collaborator' => [
                                'id',
                                'name',
                                'profile_image_url'
                            ]
                        ]
                    ]
                ]
            ]);
    }

    #[Test]
    public function collaboratorCanViewBlacklistedDomains(): void
    {
        $collaborator = UserFactory::new()->create();

        $folder = FolderFactory::new()->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->loginUser($collaborator);
        $this->fetchBlacklistDomainsResponse(['folder_id' => $folder->public_id->present()])->assertOk();
    }

    #[Test]
    public function whenCollaboratorNoLongerExists(): void
    {
        $blacklistDomain = new BlacklistDomain();

        [$folderOwner, $collaborator] = UserFactory::times(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $blacklistDomain->create($folder, $collaborator, new Url('https://forbidden-url.com'));

        $collaborator->delete();

        $this->loginUser($folderOwner);
        $this->fetchBlacklistDomainsResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(4, 'data.0.attributes')
            ->assertJsonMissingPath('data.0.attributes.collaborator')
            ->assertJsonPath('data.0.attributes.collaborator_exists', false);
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        $folder = FolderFactory::new()->create();

        $this->loginUser(UserFactory::new()->create());
        $this->fetchBlacklistDomainsResponse(['folder_id' => $folder->public_id->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $this->loginUser(UserFactory::new()->create());
        $this->fetchBlacklistDomainsResponse(['folder_id' => $this->generateFolderId()->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    #[Test]
    public function willReturnNotFoundWhenFolderOwnerDoesNotExists(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        $folderOwner->delete();

        $this->loginUser($collaborator);
        $this->fetchBlacklistDomainsResponse(['folder_id' => $folder->public_id->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }
}
