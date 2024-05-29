<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder\Blacklisting;

use App\Http\Handlers\Blacklisting\Handler;
use App\Models\User;
use App\Rules\UrlRule;
use App\ValueObjects\PublicId\FolderPublicId;
use App\ValueObjects\Url;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BlacklistDomainController
{
    public function __invoke(Request $request, Handler $handler, string $folderId): JsonResponse
    {
        $folderId = FolderPublicId::fromRequest($folderId);

        $request->validate(['url' => ['required', new UrlRule()]]);

        $handler->handle($folderId, User::fromRequest($request), new Url($request->input('url')));

        return new JsonResponse(status: JsonResponse::HTTP_CREATED);
    }
}
