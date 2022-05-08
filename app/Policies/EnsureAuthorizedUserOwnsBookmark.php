<?php

declare(strict_types=1);

namespace App\Policies;

use App\DataTransferObjects\Bookmark;
use App\ValueObjects\UserID;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class EnsureAuthorizedUserOwnsBookmark
{
    public function __invoke(Bookmark $bookmark): void
    {
        if (!UserID::fromAuthUser()->equals($bookmark->ownerId)) {
            throw new HttpException(Response::HTTP_FORBIDDEN);
        }
    }
}
