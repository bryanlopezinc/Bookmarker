<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\HttpException;
use App\Repositories\UserRepository;
use App\ValueObjects\Email;
use App\ValueObjects\UserID;

final class RemoveEmailService
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function delete(UserID $userID, Email $email): void
    {
        $emailExist = false;

        foreach ($this->userRepository->getUserSecondaryEmails($userID) as $userEmail) {
            if ($email->equals($userEmail)) {
                $emailExist = true;
            }
        }

        if ($emailExist === false) {
            throw HttpException::notFound(['message' => 'Email does not exist']);
        }

        $this->userRepository->deleteSecondaryEmail($email);
    }
}
