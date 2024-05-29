<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder\Blacklisting;

use App\Http\Handlers\WhitelistDomain\Handler;
use App\Models\User;
use App\ValueObjects\PublicId\BlacklistedDomainId;
use App\ValueObjects\PublicId\FolderPublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WhitelistDomainController
{
    public function __invoke(Request $request, Handler $handler, string $folderId, string $domainId): JsonResponse
    {
        $handler->handle(
            FolderPublicId::fromRequest($folderId),
            User::fromRequest($request),
            BlacklistedDomainId::fromRequest($domainId)
        );

        return new JsonResponse();
    }
}
