<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Updating;

use App\Enums\Permission;
use App\Http\Handlers\SuspendCollaborator\SuspendCollaborator;
use Database\Factories\UserFactory;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Traits\CreatesCollaboration;

class SuspendedCollaboratorTest extends TestCase
{
    use CreatesCollaboration;

    #[Test]
    public function suspendedCollaboratorCannotUpdateFolder(): void
    {
        $this->loginUser($suspendedCollaborator = UserFactory::new()->create());

        $folder = FolderFactory::new()->create();

        $this->CreateCollaborationRecord($suspendedCollaborator, $folder, Permission::updateFolderTypes());

        SuspendCollaborator::suspend($suspendedCollaborator, $folder);

        $this->updateFolderResponse([
            'name'       => 'foo',
            'folder_id'  => $folder->public_id->present(),
        ])->assertForbidden()->assertJsonFragment(['message' => 'CollaboratorSuspended']);

        $this->assertEquals($folder->name, $folder->refresh()->name);
    }

    #[Test]
    public function suspendedCollaboratorCanUpdateFolderWhenSuspensionDurationIsPast(): void
    {
        $this->loginUser($suspendedCollaborator = UserFactory::new()->create());

        $folder = FolderFactory::new()->create();

        $this->CreateCollaborationRecord($suspendedCollaborator, $folder, Permission::updateFolderTypes());

        SuspendCollaborator::suspend($suspendedCollaborator, $folder, suspensionDurationInHours: 1);

        $this->travel(57)->minutes(function () use ($folder) {
            $this->updateFolderResponse([
                'name'       => 'foo',
                'folder_id'  => $folder->public_id->present(),
            ])->assertForbidden()->assertJsonFragment(['message' => 'CollaboratorSuspended']);
        });

        $this->travel(62)->minutes(function () use ($folder) {
            $this->updateFolderResponse([
                'name'       => 'foo',
                'folder_id'  => $folder->public_id->present(),
            ])->assertSuccessful();
        });

        $this->assertTrue($folder->suspendedCollaborators->isEmpty());
    }
}
