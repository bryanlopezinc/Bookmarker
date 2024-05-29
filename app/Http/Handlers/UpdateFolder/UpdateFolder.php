<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder;

use App\DataTransferObjects\UpdateFolderRequestData;
use App\Enums\FolderVisibility;
use App\Exceptions\FolderNotModifiedAfterOperationException;
use App\Filesystem\FoldersIconsFilesystem;
use App\FolderSettings\FolderSettings;
use App\Http\Handlers\HasHandlersInterface;
use App\Models\Folder;
use App\ValueObjects\FolderName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Arr;

final class UpdateFolder implements Scope, HasHandlersInterface
{
    private readonly UpdateFolderRequestData $data;
    private readonly FoldersIconsFilesystem $filesystem;
    private readonly SendFolderUpdatedNotification $sendNotificationAction;

    public function __construct(
        UpdateFolderRequestData $data,
        SendFolderUpdatedNotification $sendNotificationAction,
        FoldersIconsFilesystem $filesystem = null
    ) {
        $this->data = $data;
        $this->sendNotificationAction = $sendNotificationAction;
        $this->filesystem = $filesystem ??= new FoldersIconsFilesystem();
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->addSelect(['name', 'description', 'settings', 'icon_path']);
    }

    /**
     * @inheritdoc
     */
    public function getHandlers(): array
    {
        return [$this->sendNotificationAction];
    }

    public function __invoke(Folder $original): void
    {
        $folder = clone $original;

        $newVisibility = fn () => FolderVisibility::fromRequest($this->data->visibility);

        if ($this->data->isUpdatingName) {
            $folder->name = new FolderName($this->data->name);
        }

        if ($this->data->isUpdatingDescription) {
            $folder->description = $this->data->description;
        }

        if ($this->data->isUpdatingSettings) {
            $folder->settings = new FolderSettings(array_replace_recursive(
                $folder->settings->toArray(),
                $this->data->settings
            ));
        }

        if ($this->data->isUpdatingVisibility) {
            if ($folder->visibility->isPasswordProtected() && ! $newVisibility()->isPasswordProtected()) {
                $folder->password = null;
            }

            $folder->visibility = $newVisibility();
        }

        if ($this->data->isUpdatingFolderPassword) {
            $folder->password = $this->data->folderPassword;
        }

        if ($this->isUpdatingIcon($folder)) {
            $folder->icon_path = $this->data->icon ? $this->filesystem->store($this->data->icon) : null;
            $this->filesystem->delete($original->icon_path);
        }

        if ( ! $folder->isDirty()) {
            throw new FolderNotModifiedAfterOperationException();
        }

        $folder->save();

        $this->sendNotificationAction->setChanges(Arr::only(
            $folder->getChanges(),
            ['name', 'description', 'icon_path']
        ));
    }

    private function isUpdatingIcon(Folder $folder): bool
    {
        if ($this->data->icon !== null) {
            return true;
        }

        return $folder->icon_path !== null && $this->data->isUpdatingIcon;
    }
}
