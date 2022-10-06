<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\Builders\UserBuilder;
use App\Exceptions\HttpException;
use App\Repositories\UserRepository;
use App\ValueObjects\Email;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class RemoveEmailService
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function delete(Request $request): void
    {
        // @phpstan-ignore-next-line
        $user = UserBuilder::fromModel($request->user('api'))->build();
        $email = new Email($request->input('email'));
        $emailExist = false;

        if ($user->email->equals($email)) {
            throw new HttpException(['message' => 'Cannot remove primary email'], Response::HTTP_BAD_REQUEST);
        }

        foreach ($this->userRepository->getUserSecondaryEmails($user->id) as $userEmail) {
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
