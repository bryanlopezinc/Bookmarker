<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Http\Request;

enum FolderVisibility: int
{
    case PUBLIC  = 2;
    case PRIVATE = 3;

    public static function fromRequest(Request $request, string $key = 'visibility'): self
    {
        return match ($request->input($key)) {
            default   => self::PUBLIC,
            'public'  => self::PUBLIC,
            'private' => self::PRIVATE
        };
    }

    public function toWord(): string
    {
        return match ($this) {
            self::PRIVATE => 'private',
            self::PUBLIC  => 'public'
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

    /**
     * @return array<int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
