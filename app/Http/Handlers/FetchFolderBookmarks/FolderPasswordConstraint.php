<?php

declare(strict_types=1);

namespace App\Http\Handlers\FetchFolderBookmarks;

use App\DataTransferObjects\FetchFolderBookmarksRequestData as Data;
use App\Exceptions\HttpException;
use App\Models\Folder;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Http\Response;

final class FolderPasswordConstraint implements Scope
{
    private readonly Data $data;
    private readonly Hasher $hasher;
    public bool $stopRequestHandling = false;

    public function __construct(Data $data, Hasher $hasher = null)
    {
        $this->data = $data;
        $this->hasher = $hasher ??= app(Hasher::class);
    }

    public function apply(Builder $builder, Model $model)
    {
        $builder->addSelect(['visibility', 'password', 'user_id']);
    }

    public function __invoke(Folder $folder): void
    {
        $folderBelongsToAuthUser = $this->data->authUser->exists && $folder->user_id === $this->data->authUser->id;

        if ( ! $folder->visibility->isPasswordProtected() || $folderBelongsToAuthUser) {
            return;
        }

        if ($this->data->password === null) {
            throw new HttpException(
                ['message' => 'PasswordRequired', 'info' => 'A password is required to view folder bookmarks'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if ($folder->password && ! $this->hasher->check($this->data->password, $folder->password)) {
            throw new HttpException(
                ['message' => 'InvalidFolderPassword', 'info' => 'The given folder password is invalid'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $this->stopRequestHandling = true;
    }
}
