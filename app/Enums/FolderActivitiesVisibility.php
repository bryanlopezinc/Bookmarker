<?php

declare(strict_types=1);

namespace App\Enums;

enum FolderActivitiesVisibility: int
{
    case PUBLIC        = 2;
    case PRIVATE       = 3;
    case COLLABORATORS = 4;

    public static function fromRequest(string $id): self
    {
        return match ($id) {
            'public'  => self::PUBLIC,
            'private' => self::PRIVATE,
            default   => self::COLLABORATORS
        };
    }

    public static function publicIdentifiers(): array
    {
        return [
            self::PUBLIC->value         => 'public',
            self::PRIVATE->value        => 'private',
            self::COLLABORATORS->value  => 'collaborators',
        ];
    }

    public function isPublic(): bool
    {
        return $this == self::PUBLIC;
    }

    public function isVisibleToCollaboratorsOnly(): bool
    {
        return $this == self::COLLABORATORS;
    }
}
