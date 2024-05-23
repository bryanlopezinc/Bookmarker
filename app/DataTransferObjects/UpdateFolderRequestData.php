<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Http\UploadedFile;

final class UpdateFolderRequestData
{
    public readonly User $authUser;
    public readonly string $visibility;
    public readonly bool $isUpdatingVisibility;
    public readonly string $userPassword;
    public readonly bool $userPasswordIsSet;
    public readonly string $folderPassword;
    public readonly bool $isUpdatingFolderPassword;
    public readonly string $name;
    public readonly bool $isUpdatingName;
    public readonly ?string $description;
    public readonly bool $isUpdatingDescription;
    public readonly array $settings;
    public readonly bool $isUpdatingSettings;
    public readonly ?UploadedFile $icon;
    public readonly bool $isUpdatingIcon;

    private function __construct(array $data)
    {
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }
    }

    public static function fromRequest(Request $request): self
    {
        $missingValuePlaceHolder = new MissingValue();

        $data = [
            'authUser'                 => User::fromRequest($request),
            'visibility'               => $request->input('visibility', $missingValuePlaceHolder),
            'isUpdatingVisibility'     => $request->has('visibility'),
            'userPassword'             => $request->input('password', $missingValuePlaceHolder),
            'userPasswordIsSet'        => $request->has('password'),
            'folderPassword'           => $request->input('folder_password', $missingValuePlaceHolder),
            'isUpdatingFolderPassword' => $request->has('folder_password'),
            'name'                     => $request->input('name', $missingValuePlaceHolder),
            'isUpdatingName'           => $request->has('name'),
            'description'              => $request->input('description'),
            'isUpdatingDescription'    => $request->has('description'),
            'settings'                 => $request->input('settings', $missingValuePlaceHolder),
            'isUpdatingSettings'       => $request->has('settings'),
            'icon'                     => $request->file('icon'),
            'isUpdatingIcon'           => $request->has('icon')
        ];

        return new self(array_filter($data, fn ($value) => ! $value instanceof MissingValue));
    }
}
