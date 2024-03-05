<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder;

use App\Models\Folder;
use App\Http\Handlers\Constraints;
use App\Contracts\FolderRequestHandlerInterface as HandlerInterface;
use App\Http\Handlers\RequestHandlersQueue;

final class Handler
{
    /**
     * @var array<class-string<HandlerInterface>>
     */
    private const HANDLERS = [
        Constraints\FolderExistConstraint::class,
        Constraints\MustBeACollaboratorConstraint::class,
        Constraints\PermissionConstraint::class,
        CanUpdateAttributesConstraint::class,
        CannotMakeFolderWithCollaboratorPrivateConstraint::class,
        Constraints\FeatureMustBeEnabledConstraint::class,
        PasswordCheckConstraint::class,
        CanUpdateOnlyProtectedFolderPasswordConstraint::class,
        UpdateFolder::class
    ];

    private RequestHandlersQueue $requestHandlersQueue;

    public function __construct()
    {
        $this->requestHandlersQueue = new RequestHandlersQueue(self::HANDLERS);
    }

    public function handle(int $folderId): void
    {
        $query = Folder::query()->select(['id'])->whereKey($folderId);

        $this->requestHandlersQueue->scope($query);

        $folder = $query->firstOr(fn () => new Folder());

        $this->requestHandlersQueue->handle(function (HandlerInterface $handler) use ($folder) {
            $handler->handle($folder);
        });
    }
}
