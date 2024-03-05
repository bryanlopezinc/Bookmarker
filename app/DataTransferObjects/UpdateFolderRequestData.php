<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Models\User;
use Illuminate\Http\Request;

final class UpdateFolderRequestData
{
    public function __construct(
        public readonly User $authUser,
        public readonly ?string $visibility,
        public readonly ?string $userPassword,
        public readonly ?string $folderPassword,
        public readonly ?string $name,
        public readonly ?string $description,
        public readonly bool $hasDescription,
        public readonly array $settings
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        /** @var User */
        $authUser = $request->user();

        return new self(
            $authUser,
            $request->input('visibility'),
            $request->input('password'),
            $request->input('folder_password'),
            $request->input('name'),
            $request->input('description'),
            $request->has('description'),
            $request->input('settings', [])
        );
    }
}
