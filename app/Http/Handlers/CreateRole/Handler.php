<?php

declare(strict_types=1);

namespace App\Http\Handlers\CreateRole;

use App\Models\Folder;
use App\Http\Handlers\Constraints;
use App\Contracts\FolderRequestHandlerInterface as HandlerInterface;
use App\DataTransferObjects\CreateFolderRoleData;
use App\Http\Handlers\RequestHandlersQueue;
use App\UAC;

final class Handler
{
    public function handle(int $folderId, CreateFolderRoleData $data): void
    {
        $requestHandlersQueue = new RequestHandlersQueue($this->getConfiguredHandlers($data));

        $query = Folder::query()->select(['id'])->whereKey($folderId);

        $requestHandlersQueue->scope($query);

        $folder = $query->firstOrNew();

        $requestHandlersQueue->handle(function (HandlerInterface $handler) use ($folder) {
            $handler->handle($folder);
        });
    }

    private function getConfiguredHandlers(CreateFolderRoleData $data): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            new Constraints\CanCreateOrModifyRoleConstraint($data->authUser),
            new Constraints\UniqueRoleNameConstraint($data->name),
            new UniqueRoleConstraint(UAC::fromRequest($data->permissions)),
            new CreateFolderRole($data)
        ];
    }
}
