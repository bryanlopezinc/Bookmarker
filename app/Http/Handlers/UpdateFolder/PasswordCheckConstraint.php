<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder;

use App\DataTransferObjects\UpdateFolderRequestData;
use App\Enums\FolderVisibility;
use App\Exceptions\HttpException;
use App\Models\Folder;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

final class PasswordCheckConstraint implements Scope
{
    private readonly Hasher $hasher;
    private readonly UpdateFolderRequestData $data;

    public function __construct(UpdateFolderRequestData $data, Hasher $hasher = null)
    {
        $this->hasher = $hasher ?: app('hash');
        $this->data = $data;
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->addSelect(['visibility']);
    }

    public function __invoke(Folder $folder): void
    {
        if( ! $this->data->isUpdatingVisibility) {
            return;
        }

        $newVisibility = FolderVisibility::fromRequest($this->data->visibility);

        $isChangingPrivateFolderVisibilityToPublic = $newVisibility->isPublic() && ! $folder->visibility->isPublic();

        if ( ! $isChangingPrivateFolderVisibilityToPublic) {
            return;
        }

        if ( ! $this->data->userPasswordIsSet) {
            throw ValidationException::withMessages(['password' => 'The Password field is required for this action.']);
        }

        if ( ! $this->hasher->check($this->data->userPassword, $this->data->authUser->password)) {
            throw new HttpException(
                ['message' => 'InvalidPasswordForFolderUpdate'],
                Response::HTTP_UNAUTHORIZED
            );
        }
    }
}
