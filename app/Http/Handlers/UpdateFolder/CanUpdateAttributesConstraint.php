<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder;

use App\DataTransferObjects\UpdateFolderRequestData;
use App\Exceptions\HttpException;
use App\Models\Folder;

final class CanUpdateAttributesConstraint
{
    public function __construct(private readonly UpdateFolderRequestData $data)
    {
    }

    public function __invoke(Folder $folder): void
    {
        if ($folder->wasCreatedBy($this->data->authUser)) {
            return;
        }

        if (
            $this->data->isUpdatingVisibility ||
            $this->data->isUpdatingSettings ||
            $this->data->isUpdatingFolderPassword
        ) {
            throw HttpException::forbidden([
                'message' => 'CannotUpdateFolderAttribute',
                'info'    => 'The request could not be completed due to inadequate permission.'
            ]);
        }
    }
}
