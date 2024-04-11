<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Http\Request;

enum FolderVisibility: int
{
    case PUBLIC             = 2;
    case PRIVATE            = 3;
    case COLLABORATORS      = 4;
    case PASSWORD_PROTECTED = 5;

    public static function fromRequest(Request|string $request = 'public', string $key = 'visibility'): self
    {
        if ($request instanceof Request) {
            $request = $request->input($key);
        }

        return match ($request) {
            'private'            => self::PRIVATE,
            'collaborators'      => self::COLLABORATORS,
            'password_protected' => self::PASSWORD_PROTECTED,
            'public'             => self::PUBLIC,
            default              => self::PUBLIC,
        };
    }

    public function toWord(): string
    {
        return match ($this) {
            self::PRIVATE            => 'private',
            self::PUBLIC             => 'public',
            self::COLLABORATORS      => 'collaborators',
            self::PASSWORD_PROTECTED => 'password_protected'
        };
    }

    public function isPublic(): bool
    {
        return $this == self::PUBLIC;
    }

    public function isPrivate(): bool
    {
        return $this == self::PRIVATE;
    }

    public function isVisibleToCollaboratorsOnly(): bool
    {
        return $this == self::COLLABORATORS;
    }

    public function isPasswordProtected(): bool
    {
        return $this == self::PASSWORD_PROTECTED;
    }
}
