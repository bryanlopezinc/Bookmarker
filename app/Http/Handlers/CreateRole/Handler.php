<?php

declare(strict_types=1);

namespace App\Http\Handlers\CreateRole;

use App\Models\Folder;
use App\Http\Handlers\Constraints;
use App\DataTransferObjects\CreateFolderRoleData;
use App\Http\Handlers\RequestHandlersQueue;
use App\Models\Scopes\WherePublicIdScope;
use App\UAC;
use App\ValueObjects\PublicId\FolderPublicId;

final class Handler
{
    public function handle(FolderPublicId $folderId, CreateFolderRoleData $data): void
    {
        $requestHandlersQueue = new RequestHandlersQueue($this->getConfiguredHandlers($data));

        $query = Folder::query()->select(['id'])->tap(new WherePublicIdScope($folderId));

        $requestHandlersQueue->scope($query);

        $requestHandlersQueue->handle($query->firstOrNew());
    }

    private function getConfiguredHandlers(CreateFolderRoleData $data): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            new Constraints\MustHaveRoleAccessConstraint($data->authUser),
            new Constraints\UniqueRoleNameConstraint($data->name),
            new UniqueRoleConstraint(UAC::fromRequest($data->permissions)),
            new CreateFolderRole($data)
        ];
    }
}
