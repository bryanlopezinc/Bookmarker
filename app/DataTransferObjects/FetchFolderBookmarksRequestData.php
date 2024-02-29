<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Models\User;
use App\PaginationData;
use Illuminate\Http\Request;

final class FetchFolderBookmarksRequestData
{
    public function __construct(
        public readonly ?string $password,
        public readonly ?User $authUser,
        public readonly PaginationData $pagination
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            $request->input('folder_password'),
            $request->user(),
            PaginationData::fromRequest($request)
        );
    }
}
