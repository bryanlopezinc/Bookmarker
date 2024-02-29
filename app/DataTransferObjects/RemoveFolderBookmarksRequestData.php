<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Models\User;
use Illuminate\Http\Request;

final class RemoveFolderBookmarksRequestData
{
    public function __construct(
        public readonly User $authUser,
        public readonly array $bookmarkIds,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        /** @var User */
        $authUser = $request->user();

        return new self(
            $authUser,
            $request->input('bookmarks', []),
        );
    }
}
