<?php

declare(strict_types=1);

namespace App\Policies;

use App\DataTransferObjects\Folder;
use App\ValueObjects\UserID;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class EnsureAuthorizedUserOwnsFolder
{
    public function __invoke(Folder $folder): void
    {
        if (!UserID::fromAuthUser()->equals($folder->ownerID)) {
            throw new HttpException(Response::HTTP_FORBIDDEN);
        }
    }
}
