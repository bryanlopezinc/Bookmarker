<?php

declare(strict_types=1);

namespace Tests\Unit\FolderSettings\Settings;

use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\FolderSettings\TestCase;

class AcceptInviteConstraintsTest extends TestCase
{
    #[Test]
    public function acceptInviteConstraints(): void
    {
        $settings = $this->make();
        $this->assertTrue($settings->acceptInviteConstraints()->value()->isEmpty());

        $settings = $this->make(['accept_invite_constraints' => ['InviterMustHaveRequiredPermission']]);
        $this->assertTrue($settings->acceptInviteConstraints()->value()->inviterMustHaveRequiredPermission());

        $settings = $this->make(['accept_invite_constraints' => ['InviterMustBeAnActiveCollaborator']]);
        $this->assertTrue($settings->acceptInviteConstraints()->value()->inviterMustBeAnActiveCollaborator());

        $settings = $this->make(['accept_invite_constraints' => ['InviterMustHaveRequiredPermission', 'InviterMustBeAnActiveCollaborator']]);
        $this->assertEquals($settings->toArray(), [
            'version' => '1.0.0',
            'accept_invite_constraints' => [
                'InviterMustHaveRequiredPermission',
                'InviterMustBeAnActiveCollaborator'
            ]
        ]);

        $this->assertFalse($this->isValid(
            ['accept_invite_constraints' => ['InviterMustBeAnActiveCollaborator', 'InviterMustBeAnActiveCollaborator']],
            'The accept_invite_constraints field has a duplicate value.'
        ));

        $this->assertFalse($this->isValid(
            ['accept_invite_constraints' => null],
            'The accept_invite_constraints must be an array.'
        ));

        $this->assertFalse($this->isValid(
            ['accept_invite_constraints' => 'InviterMustBeAnActiveCollaborator'],
            'The accept_invite_constraints must be an array.'
        ));
    }
}
