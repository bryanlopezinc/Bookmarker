<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Contracts\IdGeneratorInterface;
use App\Enums\TwoFaMode;
use App\Events\RegisteredEvent;
use App\Filesystem\ProfileImageFileSystem;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use App\Services\UpdateProfileService;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\JsonResponse;

final class UserController
{
    public function __construct(
        private readonly Hasher $hasher,
        private readonly ProfileImageFileSystem $filesystem,
        private readonly IdGeneratorInterface $idGenerator
    ) {
    }

    public function __invoke(CreateUserRequest $request): JsonResponse
    {
        $profileImagePath = $request->has('profile_photo') ?
            $this->filesystem->store($request->allFiles()['profile_photo']) :
            null;

        $user = User::query()->create([
            'public_id'          => $this->idGenerator->generate(),
            'username'           => $request->validated('username'),
            'first_name'          => $request->validated('first_name'),
            'last_name'          => $request->validated('last_name'),
            'email'              => $request->validated('email'),
            'password'           => $this->hasher->make($request->validated('password')),
            'two_fa_mode'        => TwoFaMode::NONE,
            'profile_image_path'  => $profileImagePath
        ]);

        event(new RegisteredEvent($user));

        return response()->json(status: JsonResponse::HTTP_CREATED);
    }

    public function update(UpdateProfileRequest $request, UpdateProfileService $service): JsonResponse
    {
        $service($request);

        return new JsonResponse();
    }
}
