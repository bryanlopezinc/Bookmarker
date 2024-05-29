<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Models\User;
use Illuminate\Http\Request;

final class AddBookmarksToFolderRequestData
{
    /**
     * @param array<string> $bookmarksPublicIds
     * @param array<string> $makeHidden
     */
    public function __construct(
        public readonly User $authUser,
        public readonly array $bookmarksPublicIds,
        public readonly array $makeHidden,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            User::fromRequest($request),
            $request->input('bookmarks'),
            $request->input('make_hidden', [])
        );
    }
}
