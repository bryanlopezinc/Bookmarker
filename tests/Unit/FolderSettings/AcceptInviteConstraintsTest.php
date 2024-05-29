<?php

declare(strict_types=1);

namespace Tests\Unit\FolderSettings;

use App\FolderSettings\AcceptInviteConstraints;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AcceptInviteConstraintsTest extends TestCase
{
    #[Test]
    public function willThrowExceptionWhenContainsDuplicateValues(): void
    {
        $this->expectExceptionMessage('Constraints contains duplicate values [InviterMustBeAnActiveCollaborator]');

        $this->make(['InviterMustBeAnActiveCollaborator', 'InviterMustBeAnActiveCollaborator']);
    }

    private function make(array $constraints = []): AcceptInviteConstraints
    {
        return new AcceptInviteConstraints($constraints);
    }

    #[Test]
    public function willThrowExceptionWhenContainsUnknownValues(): void
    {
        $this->expectExceptionMessage('Unknown Constraints values [foo]');

        $this->make(['InviterMustBeAnActiveCollaborator', 'foo']);
    }
}
