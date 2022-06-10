<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\DataTransferObjects\Builders\FolderBuilder;
use App\Repositories\FoldersRepository;
use App\ValueObjects\UserID;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CreateFolderController
{
    public function __invoke(Request $request, FoldersRepository $repository): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:150']
        ]);

        $folder = (new FolderBuilder())
            ->setCreatedAt(now())
            ->setDescription($request->input('description'))
            ->setName($request->input('name'))
            ->setOwnerID(UserID::fromAuthUser())
            ->build();

        $repository->create($folder);

        return response()->json(status: Response::HTTP_CREATED);
    }
}
