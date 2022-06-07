<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\BelongsToUserInterface;
use App\ValueObjects\UserID;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class EnsureAuthorizedUserOwnsResource
{
    public function __invoke(BelongsToUserInterface $userResource): void
    {
        if (!UserID::fromAuthUser()->equals($userResource->getOwnerID())) {
            throw new HttpException(Response::HTTP_FORBIDDEN);
        }
    }
}
