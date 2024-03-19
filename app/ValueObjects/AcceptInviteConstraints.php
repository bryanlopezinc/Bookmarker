<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Contracts\FolderSettingValueInterface;
use Illuminate\Support\Collection;
use DomainException;
use Exception;

final class AcceptInviteConstraints implements FolderSettingValueInterface
{
    private const VALID = [
        'InviterMustBeAnActiveCollaborator',
        'InviterMustHaveRequiredPermission',
    ];

    private readonly Collection $constraints;

    public function __construct(array $constraints)
    {
        $this->constraints = new Collection($constraints);

        $duplicates = $this->constraints->duplicatesStrict();
        $unknown = $this->constraints->diff(self::VALID);

        if ($unknown->isNotEmpty()) {
            throw new DomainException(sprintf('Unknown Constraints values [%s]', $unknown->implode(',')));
        }

        if ($duplicates->isNotEmpty()) {
            throw new Exception(sprintf('Constraints contains duplicate values [%s]', $duplicates->implode(',')));
        }
    }

    public function inviterMustBeAnActiveCollaborator(): bool
    {
        return $this->has('InviterMustBeAnActiveCollaborator');
    }

    public function inviterMustHaveRequiredPermission(): bool
    {
        return $this->has('InviterMustHaveRequiredPermission');
    }

    private function has(string $key): bool
    {
        return $this->constraints->containsStrict($key);
    }

    public function isEmpty(): bool
    {
        return $this->constraints->isEmpty();
    }
}
