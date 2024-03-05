<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class CreateFolderData
{
    public function __construct(
        public readonly ?string $description,
        public readonly string $name,
        public readonly User $owner,
        public ?string $visibility,
        public readonly array $settings,
        public readonly ?string $password
    ) {
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray([
            'description' => $request->validated('description'),
            'name'        => $request->validated('name'),
            'owner'       => $request->user(),
            'visibility'  => $request->validated('visibility'),
            'settings'    => $request->validated('settings', []),
            'password'    => $request->validated('folder_password')
        ]);
    }

    public static function fromArray(array $data): self
    {
        return new self(...[
            'description' => $data['description'],
            'name'        => $data['name'],
            'owner'       => $data['owner'],
            'visibility'  => $data['visibility'],
            'settings'    => $data['settings'],
            'password'    => $data['password']
        ]);
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
