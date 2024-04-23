<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder;

use App\DataTransferObjects\UpdateFolderRequestData;
use App\Enums\FolderVisibility;
use App\Exceptions\FolderNotModifiedAfterOperationException;
use App\Filesystem\FolderThumbnailFileSystem;
use App\Models\Folder;
use App\Utils\FolderSettingsNormalizer;
use App\ValueObjects\FolderName;
use App\ValueObjects\FolderSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class UpdateFolder implements Scope
{
    private readonly UpdateFolderRequestData $data;
    private readonly object $notificationSender;
    private readonly FolderSettingsNormalizer $normalizer;
    private readonly FolderThumbnailFileSystem $filesystem;

    public function __construct(
        UpdateFolderRequestData $data,
        object $notificationSender,
        FolderSettingsNormalizer $normalizer = null,
        FolderThumbnailFileSystem $filesystem = null
    ) {
        $this->data = $data;
        $this->notificationSender = $notificationSender;
        $this->normalizer = $normalizer ??= new FolderSettingsNormalizer();
        $this->filesystem = $filesystem ??= new FolderThumbnailFileSystem();
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->addSelect(['name', 'description', 'settings', 'icon_path']);

        if ($this->notificationSender instanceof Scope) {
            $this->notificationSender->apply($builder, $model);
        }
    }

    public function __invoke(Folder $updatable): void
    {
        $folder = clone $updatable;
        $notificationSender = $this->notificationSender;

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
                $this->normalizer->fromRequest($this->data->settings)
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

        if ($this->isUpdatingThumbnail($updatable)) {
            $folder->icon_path = $this->data->thumbnail ? $this->filesystem->store($this->data->thumbnail) : null;
            $this->filesystem->delete($updatable->icon_path);
        }

        if ( ! $folder->isDirty()) {
            throw new FolderNotModifiedAfterOperationException();
        }

        $updatedFolder = clone $folder;

        $folder->save();

        $notificationSender($updatedFolder); //@phpstan-ignore-line
    }

    private function isUpdatingThumbnail(Folder $folder): bool
    {
        if ($this->data->thumbnail !== null) {
            return true;
        }

        return $folder->icon_path !== null && $this->data->isUpdatingThumbnail;
    }
}
