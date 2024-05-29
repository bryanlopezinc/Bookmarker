<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Blacklist;

use App\Actions\ToggleFolderFeature;
use App\Enums\ActivityType;
use App\Enums\CollaboratorMetricType;
use App\Enums\Feature;
use App\Enums\Permission;
use App\DataTransferObjects\Activities\DomainBlacklistedActivityLogData as ActivityLogData;
use App\FolderSettings\Settings\Activities\LogActivities;
use App\FolderSettings\Settings\Activities\LogDomainBlacklistedActivity;
use App\Models\BlacklistedDomain;
use App\UAC;
use App\ValueObjects\Url;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Folder\Concerns\AssertFolderCollaboratorMetrics;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;
use Tests\Traits\CreatesRole;
use Tests\Traits\GeneratesId;

class BlacklistDomainTest extends TestCase
{
    use GeneratesId;
    use CreatesCollaboration;
    use CreatesRole;
    use AssertFolderCollaboratorMetrics;
    use WithFaker;

    private function blacklistDomainResponse(array $data = []): TestResponse
    {
        return $this->postJson(
            route('blacklistDomain', Arr::only($data, ['folder_id'])),
            Arr::except($data, ['folder_id'])
        );
    }

    #[Test]
    public function route(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/bookmarks/domains/blacklist', 'blacklistDomain');
    }

    #[Test]
    public function willReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->blacklistDomainResponse(['folder_id' => 5])->assertUnauthorized();
    }

    #[Test]
    public function willReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $url = $this->faker->url;

        $folderId = $this->generateFolderId()->present();

        $this->loginUser();

        $this->blacklistDomainResponse(['folder_id' => 'foo', 'url' => $url])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->blacklistDomainResponse(['folder_id' => $folderId, 'url' => 'foo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url' => 'The url must be a valid url']);

        $this->blacklistDomainResponse(['folder_id' => $folderId, 'url' => 'chrome://version'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);
    }

    #[Test]
    public function blacklistDomain(): void
    {
        $folderOwner = UserFactory::new()->create();

        $folder = FolderFactory::new()->for($folderOwner)->create([
            'created_at' => $createdAt = now()->subDay(),
            'updated_at' => $createdAt,
        ]);

        $url = new Url('https://forbidden-url.com/bad-page/contents');

        $this->loginUser($folderOwner);
        $this->blacklistDomainResponse([
            'folder_id' => $folder->public_id->present(),
            'url'       => $url->toString(),
        ])->assertCreated();

        /** @var BlacklistedDomain */
        $record = $folder->blacklistedDomains->sole();

        /** @var \App\Models\FolderActivity */
        $activity = $folder->activities->sole();

        $this->assertTrue($folder->refresh()->updated_at->isToday());
        $this->assertEquals($folderOwner->id, $record->created_by);
        $this->assertEquals($url->toString(), $record->given_url);
        $this->assertEquals('forbidden-url.com', $record->resolved_domain);
        $this->assertEquals($url->getDomain()->getRegisterableHash(), $record->domain_hash);
        $this->assertNoMetricsRecorded($folderOwner->id, $folder->id, CollaboratorMetricType::SUSPENDED_COLLABORATORS);
        $this->assertEquals($activity->type, ActivityType::DOMAIN_BLACKLISTED);
        $this->assertEquals($activity->data, (new ActivityLogData($folderOwner, $url))->toArray());
    }

    #[Test]
    public function collaboratorWithPermissionCanBlacklistDomain(): void
    {
        $collaboratorWithBlacklistDomainPermission = UserFactory::new()->create();

        $folder = FolderFactory::new()->create();

        $this->CreateCollaborationRecord($collaboratorWithBlacklistDomainPermission, $folder, Permission::BLACKLIST_DOMAIN);

        $this->loginUser($collaboratorWithBlacklistDomainPermission);
        $this->blacklistDomainResponse([
            'folder_id' => $folder->public_id->present(),
            'url'       => 'https://forbidden-url.com/bad-page/contents',
        ])->assertCreated();

        $this->assertFolderCollaboratorMetric($collaboratorWithBlacklistDomainPermission->id, $folder->id, $type = CollaboratorMetricType::DOMAINS_BLACKLISTED);
        $this->assertFolderCollaboratorMetricsSummary($collaboratorWithBlacklistDomainPermission->id, $folder->id, $type);
        $this->assertTrue($folder->activities->isNotEmpty());
        $this->assertTrue($folder->blacklistedDomains->isNotEmpty());
    }

    #[Test]
    public function collaboratorWithRoleCanBlacklistDomain(): void
    {
        $collaboratorWithBlacklistDomainRole = UserFactory::new()->create();

        $folder = FolderFactory::new()->create();

        $this->CreateCollaborationRecord($collaboratorWithBlacklistDomainRole, $folder, Permission::ADD_BOOKMARKS);

        $this->attachRoleToUser($collaboratorWithBlacklistDomainRole, $this->createRole('NSFW Moderator', $folder, Permission::BLACKLIST_DOMAIN));

        $this->loginUser($collaboratorWithBlacklistDomainRole);
        $this->blacklistDomainResponse([
            'folder_id' => $folder->public_id->present(),
            'url'       => 'https://forbidden-url.com/bad-page/contents',
        ])->assertCreated();

        $this->assertTrue($folder->activities->isNotEmpty());
        $this->assertTrue($folder->blacklistedDomains->isNotEmpty());
    }

    #[Test]
    public function whenFolderAlreadyHasBlacklistedDomains(): void
    {
        $folderOwner = UserFactory::new()->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->loginUser($folderOwner);
        $this->blacklistDomainResponse([
            'folder_id' => $folder->public_id->present(),
            'url'       => 'https://forbidden-url.com/bad-page/contents',
        ])->assertCreated();

        $this->blacklistDomainResponse([
            'folder_id' => $folder->public_id->present(),
            'url'       => 'https://forbidden-url2.com/bad-page/contents',
        ])->assertCreated();
    }

    #[Test]
    public function willReturnConflictWhenDomainIsAlreadyBlacklisted(): void
    {
        [$folderOwner, $collaboratorWithBlacklistDomainPermission] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaboratorWithBlacklistDomainPermission, $folder, Permission::BLACKLIST_DOMAIN);

        $this->loginUser($folderOwner);
        $this->blacklistDomainResponse($data = [
            'folder_id' => $folder->public_id->present(),
            'url'       => 'https://forbidden-url.com/bad-page/contents',
        ])->assertCreated();

        $this->refreshApplication();
        $this->loginUser($collaboratorWithBlacklistDomainPermission);
        $this->blacklistDomainResponse($data)->assertConflict()->assertJsonFragment(['message' => 'DomainAlreadyBlacklisted']);

        $this->loginUser($folderOwner);
        $this->blacklistDomainResponse($data = [
            'folder_id' => $folder->public_id->present(),
            'url'       => 'https://forbidden-url.com/blog',
        ])->assertConflict()
            ->assertJsonFragment(['message' => 'DomainAlreadyBlacklisted']);

        $this->assertCount(1, $folder->activities);
        $this->assertCount(1, $folder->blacklistedDomains);
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        $folder = FolderFactory::new()->create();

        $this->loginUser(UserFactory::new()->create());
        $this->blacklistDomainResponse([
            'folder_id' => $folder->public_id->present(),
            'url'       => 'https://forbidden-url.com',
        ])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->assertTrue($folder->blacklistedDomains->isEmpty());
        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function willReturnForbiddenWhenCollaboratorDoesNotHaveRequiredPermission(): void
    {
        $collaborator = UserFactory::new()->create();

        $folder = FolderFactory::new()->create();

        $this->CreateCollaborationRecord($collaborator, $folder, UAC::all()->except(Permission::BLACKLIST_DOMAIN)->toArray());

        $this->loginUser($collaborator);
        $this->blacklistDomainResponse([
            'url'       => 'https://forbidden-url.com',
            'folder_id' => $folder->public_id->present(),
        ])->assertForbidden()->assertJsonFragment(['message' => 'PermissionDenied']);

        $this->assertTrue($folder->blacklistedDomains->isEmpty());
        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $this->loginUser(UserFactory::new()->create());
        $this->blacklistDomainResponse([
            'folder_id' => $this->generateFolderId()->present(),
            'url'       => 'https://forbidden-url.com',
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    #[Test]
    public function willReturnNotFoundWhenFolderOwnerDoesNotExists(): void
    {
        [$folderOwner, $collaboratorWithBlacklistDomainPermission] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaboratorWithBlacklistDomainPermission, $folder, Permission::BLACKLIST_DOMAIN);

        $folderOwner->delete();

        $this->loginUser($collaboratorWithBlacklistDomainPermission);
        $this->blacklistDomainResponse([
            'folder_id' => $folder->public_id->present(),
            'url'       => 'https://forbidden-url.com/bad-page/contents',
        ])->assertNotFound()->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->assertTrue($folder->blacklistedDomains->isEmpty());
        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function whenFeatureIsDisabled(): void
    {
        $toggleFolderFeature = new ToggleFolderFeature();

        [$folderOwner, $collaboratorWithBlacklistDomainPermission] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaboratorWithBlacklistDomainPermission, $folder, Permission::BLACKLIST_DOMAIN);

        //Assert collaborator can blacklist domain when disabled feature is not blacklist domain feature.
        $toggleFolderFeature->disable($folder->id, Feature::SEND_INVITES);
        $this->loginUser($collaboratorWithBlacklistDomainPermission);
        $this->blacklistDomainResponse($data = [
            'url'       => 'https://forbidden-url.com',
            'folder_id' => $folder->public_id->present(),
        ])->assertCreated();

        $toggleFolderFeature->disable($folder->id, Feature::BLACKLIST_DOMAIN);

        //assert folder owner can blacklist domain when feature is disabled.
        $this->refreshApplication();
        $this->loginUser($folderOwner);
        $this->blacklistDomainResponse([
            'url'       => 'https://nsfw-bad-url.com',
            'folder_id' => $folder->public_id->present(),
        ])->assertCreated();

        $this->refreshApplication();
        $this->loginUser($collaboratorWithBlacklistDomainPermission);
        $this->blacklistDomainResponse($data)->assertForbidden()->assertJsonFragment(['message' => 'FolderFeatureDisAbled']);

        $this->assertCount(2, $folder->activities);
    }

    #[Test]
    public function willNotLogActivityWhenActivityLoggingIsDisabled(): void
    {
        [$folderOwner, $collaboratorWithBlacklistDomainPermission] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings([
                new LogActivities(false),
                new LogDomainBlacklistedActivity(true),
            ])
            ->create();

        $this->CreateCollaborationRecord($collaboratorWithBlacklistDomainPermission, $folder, Permission::BLACKLIST_DOMAIN);

        $this->loginUser($folderOwner);
        $this->blacklistDomainResponse([
            'folder_id' => $folder->public_id->present(),
            'url'       => 'https://forbidden-url.com',
        ])->assertCreated();

        $this->refreshApplication();
        $this->loginUser($collaboratorWithBlacklistDomainPermission);
        $this->blacklistDomainResponse([
            'folder_id' => $folder->public_id->present(),
            'url'       => 'https://forbidden-url2.com',
        ])->assertCreated();

        $this->assertCount(0, $folder->activities);
    }

    #[Test]
    public function willNotLogActivityWhenLogDomainBlacklistedActivityIsDisabled(): void
    {
        [$folderOwner, $collaboratorWithBlacklistDomainPermission] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings([
                new LogActivities(true),
                new LogDomainBlacklistedActivity(false),
            ])
            ->create();

        $this->CreateCollaborationRecord($collaboratorWithBlacklistDomainPermission, $folder, Permission::BLACKLIST_DOMAIN);

        $this->loginUser($folderOwner);
        $this->blacklistDomainResponse([
            'folder_id' => $folder->public_id->present(),
            'url'       => 'https://forbidden-url.com',
        ])->assertCreated();

        $this->refreshApplication();
        $this->loginUser($collaboratorWithBlacklistDomainPermission);
        $this->blacklistDomainResponse([
            'folder_id' => $folder->public_id->present(),
            'url'       => 'https://forbidden-url2.com',
        ])->assertCreated();

        $this->assertCount(0, $folder->activities);
    }
}
