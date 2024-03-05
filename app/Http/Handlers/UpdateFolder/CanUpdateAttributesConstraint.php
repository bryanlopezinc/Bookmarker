<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder;

use App\Contracts\FolderRequestHandlerInterface;
use App\DataTransferObjects\UpdateFolderRequestData;
use App\Exceptions\HttpException;
use App\Models\Folder;

final class CanUpdateAttributesConstraint implements FolderRequestHandlerInterface
{
    public function __construct(private readonly UpdateFolderRequestData $data)
    {
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $folderBelongsToAuthUser = $folder->user_id === $this->data->authUser->id;

        if ($folderBelongsToAuthUser) {
            return;
        }

        if ($this->data->visibility !== null || !empty($this->data->settings)) {
            throw HttpException::forbidden([
                'message' => 'CannotUpdateFolderAttribute',
                'info' => 'The request could not be completed due to inadequate permission.'
            ]);
        }
    }
}
