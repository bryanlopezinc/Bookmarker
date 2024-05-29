<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Blacklist;

use App\Actions\BlacklistDomain;
use App\Actions\ToggleFolderFeature;
use App\Enums\ActivityType;
use App\Enums\CollaboratorMetricType;
use App\Enums\Feature;
use App\Enums\Permission;
use App\DataTransferObjects\Activities\DomainWhiteListedActivity as ActivityLogData;
use App\FolderSettings\Settings\Activities\LogActivities;
use App\FolderSettings\Settings\Activities\LogDomainWhitelistedActivity;
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

class WhitelistDomainTest extends TestCase
{
    use GeneratesId;
    use CreatesCollaboration;
    use CreatesRole;
    use AssertFolderCollaboratorMetrics;
    use WithFaker;

    private function whitelistDomainResponse(array $data = []): TestResponse
    {
        return $this->deleteJson(
            route('whitelistDomain', Arr::only($data, ['folder_id', 'domain_id'])),
            Arr::except($data, ['folder_id', 'domain_id'])
        );
    }

    #[Test]
    public function route(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/bookmarks/domains/blacklist/{domain_id}', 'whitelistDomain');
    }

    #[Test]
    public function willReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->whitelistDomainResponse(['folder_id' => 5, 'domain_id' => 44])->assertUnauthorized();
    }

    #[Test]
    public function willReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $folderId = $this->generateFolderId()->present();
        $recordId = $this->generateBlacklistedDomainId()->present();

        $this->loginUser();

        $this->whitelistDomainResponse(['folder_id' => 'foo', 'domain_id' => $recordId])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->whitelistDomainResponse(['folder_id' => $folderId, 'domain_id' => 'foo'])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'RecordNotFound']);
    }

    #[Test]
    public function whitelistDomain(): void
    {
        $blacklistDomain = new BlacklistDomain();

        $folderOwner = UserFactory::new()->create();
        $folder = FolderFactory::new()->for($folderOwner)->create(['updated_at' => now()->subDay()]);

        $blacklistedDomain = $blacklistDomain->create($folder, $folderOwner, new Url('https://forbidden-url.com'));

        $this->loginUser($folderOwner);
        $this->whitelistDomainResponse([
            'folder_id' => $folder->public_id->present(),
            'domain_id' => $blacklistedDomain->public_id->present()
        ])->assertOk();

        /** @var \App\Models\FolderActivity */
        $activity = $folder->activities->sole();

        $this->assertTrue($folder->refresh()->updated_at->isToday());
        $this->assertTrue($folder->blacklistedDomains->isEmpty());
        $this->assertNoMetricsRecorded($folderOwner->id, $folder->id, CollaboratorMetricType::DOMAINS_WHITELISTED);
        $this->assertEquals($activity->type, ActivityType::DOMAIN_WHITELISTED);
        $this->assertEquals($activity->data, (new ActivityLogData($folderOwner, 'forbidden-url.com'))->toArray());
    }

    #[Test]
    public function collaboratorWithPermissionCanWhitelistDomain(): void
    {
        $blacklistDomain = new BlacklistDomain();
        $collaboratorWithPermission = UserFactory::new()->create();

        $folder = FolderFactory::new()->create();
        $blacklistedDomain = $blacklistDomain->create($folder, UserFactory::new()->create(), new Url('https://forbidden-url.com'));

        $this->CreateCollaborationRecord($collaboratorWithPermission, $folder, Permission::WHITELIST_DOMAIN);

        $this->loginUser($collaboratorWithPermission);
        $this->whitelistDomainResponse([
            'folder_id' => $folder->public_id->present(),
            'domain_id' => $blacklistedDomain->public_id->present()
        ])->assertOk();

        $this->assertTrue($folder->blacklistedDomains->isEmpty());
        $this->assertFolderCollaboratorMetric($collaboratorWithPermission->id, $folder->id, $type = CollaboratorMetricType::DOMAINS_WHITELISTED);
        $this->assertFolderCollaboratorMetricsSummary($collaboratorWithPermission->id, $folder->id, $type);
        $this->assertTrue($folder->activities->isNotEmpty());
    }

    #[Test]
    public function collaboratorWithRoleCanWhitelistDomain(): void
    {
        $blacklistDomain = new BlacklistDomain();
        $collaboratorWithBlacklistDomainRole = UserFactory::new()->create();

        $folder = FolderFactory::new()->create();
        $blacklistedDomain = $blacklistDomain->create($folder, UserFactory::new()->create(), new Url('https://forbidden-url.com'));

        $this->CreateCollaborationRecord($collaboratorWithBlacklistDomainRole, $folder, Permission::ADD_BOOKMARKS);

        $this->attachRoleToUser($collaboratorWithBlacklistDomainRole, $this->createRole('NSFW Moderator', $folder, Permission::WHITELIST_DOMAIN));

        $this->loginUser($collaboratorWithBlacklistDomainRole);
        $this->whitelistDomainResponse([
            'folder_id' => $folder->public_id->present(),
            'domain_id' => $blacklistedDomain->public_id->present()
        ])->assertOk();

        $this->assertTrue($folder->activities->isNotEmpty());
        $this->assertTrue($folder->blacklistedDomains->isEmpty());
    }

    #[Test]
    public function willReturnNotFoundWhenDomainIsAlreadyWhitelisted(): void
    {
        [$folderOwner, $collaboratorWithBlacklistDomainPermission] = UserFactory::times(2)->create();

        $blacklistDomain = new BlacklistDomain();
        $folder = FolderFactory::new()->for($folderOwner)->create();
        $blacklistedDomain = $blacklistDomain->create($folder, UserFactory::new()->create(), new Url('https://forbidden-url.com'));

        $this->CreateCollaborationRecord($collaboratorWithBlacklistDomainPermission, $folder, Permission::WHITELIST_DOMAIN);

        $this->loginUser($folderOwner);
        $this->whitelistDomainResponse($data = [
            'folder_id' => $folder->public_id->present(),
            'domain_id' => $blacklistedDomain->public_id->present()
        ])->assertOk();

        $this->refreshApplication();
        $this->loginUser($collaboratorWithBlacklistDomainPermission);
        $this->whitelistDomainResponse($data)->assertNotFound()->assertJsonFragment(['message' => 'RecordNotFound']);

        $this->assertCount(1, $folder->activities);
        $this->assertCount(0, $folder->blacklistedDomains);
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        $blacklistDomain = new BlacklistDomain();
        $folder = FolderFactory::new()->create();
        $blacklistedDomain = $blacklistDomain->create($folder, UserFactory::new()->create(), new Url('https://forbidden-url.com'));

        $this->loginUser(UserFactory::new()->create());
        $this->whitelistDomainResponse([
            'folder_id' => $folder->public_id->present(),
            'domain_id' => $blacklistedDomain->public_id->present()
        ])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->assertTrue($folder->blacklistedDomains->isNotEmpty());
        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function willReturnNotFoundWhenBlacklistedDomainIsNotAttachedToFolder(): void
    {
        $blacklistDomain = new BlacklistDomain();
        $collaboratorWithPermission = UserFactory::new()->create();

        $folder = FolderFactory::new()->create();
        $blacklistDomain->create($folder, UserFactory::new()->create(), new Url('https://forbidden-url.com'));

        $this->CreateCollaborationRecord($collaboratorWithPermission, $folder, Permission::WHITELIST_DOMAIN);

        $this->loginUser($collaboratorWithPermission);
        $this->whitelistDomainResponse([
            'folder_id' => $folder->public_id->present(),
            'domain_id' => $blacklistDomain->create(FolderFactory::new()->create(), UserFactory::new()->create(), new Url('https://forbidden-url.com'))->public_id->present()
        ])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'RecordNotFound']);

        $this->assertTrue($folder->blacklistedDomains->isNotEmpty());
        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function willReturnForbiddenWhenCollaboratorDoesNotHaveRequiredPermission(): void
    {
        $blacklistDomain = new BlacklistDomain();

        $collaborator = UserFactory::new()->create();
        $folder = FolderFactory::new()->create();

        $blacklistedDomain = $blacklistDomain->create($folder, UserFactory::new()->create(), new Url('https://forbidden-url.com'));

        $this->CreateCollaborationRecord($collaborator, $folder, UAC::all()->except(Permission::WHITELIST_DOMAIN)->toArray());

        $this->loginUser($collaborator);
        $this->whitelistDomainResponse([
            'folder_id' => $folder->public_id->present(),
            'domain_id' => $blacklistedDomain->public_id->present()
        ])->assertForbidden()->assertJsonFragment(['message' => 'PermissionDenied']);

        $this->assertTrue($folder->blacklistedDomains->isNotEmpty());
        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $this->loginUser(UserFactory::new()->create());
        $this->whitelistDomainResponse([
            'folder_id' => $this->generateFolderId()->present(),
            'domain_id' => $this->generateBlacklistedDomainId()->present(),
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    #[Test]
    public function willReturnNotFoundWhenFolderOwnerDoesNotExists(): void
    {
        $blacklistDomain = new BlacklistDomain();

        [$folderOwner, $collaboratorWithBlacklistDomainPermission] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();
        $blacklistedDomain = $blacklistDomain->create($folder, $folderOwner, new Url('https://forbidden-url.com'));

        $this->CreateCollaborationRecord($collaboratorWithBlacklistDomainPermission, $folder, Permission::WHITELIST_DOMAIN);

        $folderOwner->delete();

        $this->loginUser($collaboratorWithBlacklistDomainPermission);
        $this->whitelistDomainResponse([
            'folder_id' => $folder->public_id->present(),
            'domain_id' => $blacklistedDomain->public_id->present()
        ])->assertNotFound()->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function whenFeatureIsDisabled(): void
    {
        $toggleFolderFeature = new ToggleFolderFeature();

        [$folderOwner, $collaboratorWithBlacklistDomainPermission] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();
        $blacklistDomain = fn () => (new BlacklistDomain())->create($folder, $folderOwner, new Url('https://forbidden-url.com'));

        $this->CreateCollaborationRecord($collaboratorWithBlacklistDomainPermission, $folder, [Permission::ADD_BOOKMARKS, Permission::WHITELIST_DOMAIN]);

        //Assert collaborator can whitelist domain when disabled feature is not whitelist domain feature.
        $toggleFolderFeature->disable($folder->id, Feature::ADD_BOOKMARKS);
        $this->loginUser($collaboratorWithBlacklistDomainPermission);
        $this->whitelistDomainResponse([
            'folder_id' => $folder->public_id->present(),
            'domain_id' => $blacklistDomain()->public_id->present()
        ])->assertOk();

        $toggleFolderFeature->disable($folder->id, Feature::WHITELIST_DOMAIN);

        //assert folder owner can whitelist domain when feature is disabled.
        $this->refreshApplication();
        $this->loginUser($folderOwner);
        $this->whitelistDomainResponse($data = [
            'folder_id' => $folder->public_id->present(),
            'domain_id' => $blacklistDomain()->public_id->present()
        ])->assertOk();

        $this->refreshApplication();
        $this->loginUser($collaboratorWithBlacklistDomainPermission);
        $this->whitelistDomainResponse($data)->assertForbidden()->assertJsonFragment(['message' => 'FolderFeatureDisAbled']);

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
                new LogDomainWhitelistedActivity(true),
            ])
            ->create();

        $this->CreateCollaborationRecord($collaboratorWithBlacklistDomainPermission, $folder, Permission::WHITELIST_DOMAIN);

        $blacklistDomain = fn () => (new BlacklistDomain())->create($folder, $folderOwner, new Url('https://forbidden-url.com'));

        $this->loginUser($folderOwner);
        $this->whitelistDomainResponse([
            'folder_id' => $folder->public_id->present(),
            'domain_id' => $blacklistDomain()->public_id->present()
        ])->assertOk();

        $this->refreshApplication();
        $this->loginUser($collaboratorWithBlacklistDomainPermission);
        $this->whitelistDomainResponse([
            'folder_id' => $folder->public_id->present(),
            'domain_id' => $blacklistDomain()->public_id->present()
        ])->assertOk();

        $this->assertCount(0, $folder->activities);
    }

    #[Test]
    public function willNotLogActivityWhenLogDomainWhitelistedActivityIsDisabled(): void
    {
        [$folderOwner, $collaboratorWithBlacklistDomainPermission] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings([
                new LogActivities(true),
                new LogDomainWhitelistedActivity(false),
            ])
            ->create();

        $this->CreateCollaborationRecord($collaboratorWithBlacklistDomainPermission, $folder, Permission::WHITELIST_DOMAIN);

        $blacklistDomain = fn () => (new BlacklistDomain())->create($folder, $folderOwner, new Url('https://forbidden-url.com'));

        $this->loginUser($folderOwner);
        $this->whitelistDomainResponse([
            'folder_id' => $folder->public_id->present(),
            'domain_id' => $blacklistDomain()->public_id->present()
        ])->assertOk();

        $this->refreshApplication();
        $this->loginUser($collaboratorWithBlacklistDomainPermission);
        $this->whitelistDomainResponse([
            'folder_id' => $folder->public_id->present(),
            'domain_id' => $blacklistDomain()->public_id->present()
        ])->assertOk();

        $this->assertCount(0, $folder->activities);
    }
}
