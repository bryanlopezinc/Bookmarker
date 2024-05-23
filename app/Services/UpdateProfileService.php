<?php

declare(strict_types=1);

namespace App\Services;

use App\Filesystem\ProfileImagesFilesystem;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Contracts\Hashing\Hasher;
use App\Enums\TwoFaMode;
use App\Models\User;

final class UpdateProfileService
{
    public function __construct(private Hasher $hasher, private ProfileImagesFilesystem $filesystem)
    {
    }

    public function __invoke(UpdateProfileRequest $request): void
    {
        $user = User::fromRequest($request);

        $validated = $request->safe();

        $attributes = $validated->only(['first_name', 'last_name']);

        if ($validated->has('password')) {
            $attributes['password'] = $this->hasher->make($validated->offsetGet('password'));
        }

        if ($validated->has('two_fa_mode')) {
            $attributes['two_fa_mode'] = TwoFaMode::fromRequest($request);
        }

        if ($validated->has('profile_photo')) {
            $attributes['profile_image_path'] = $this->filesystem->store($request->allFiles()['profile_photo']);

            $this->filesystem->delete($user->profile_image_path);
        }

        $user->update($attributes);
    }
}
