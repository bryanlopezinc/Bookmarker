<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder;

use App\Contracts\FolderRequestHandlerInterface;
use App\DataTransferObjects\UpdateFolderRequestData;
use App\Enums\FolderVisibility;
use App\Exceptions\FolderNotModifiedAfterOperationException;
use App\Models\Folder;
use App\Utils\FolderSettingsNormalizer;
use App\ValueObjects\FolderName;
use App\ValueObjects\FolderSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class UpdateFolder implements FolderRequestHandlerInterface, Scope
{
    private readonly UpdateFolderRequestData $data;
    private readonly FolderRequestHandlerInterface $notificationSender;
    private readonly FolderSettingsNormalizer $normalizer;

    public function __construct(
        UpdateFolderRequestData $data,
        FolderRequestHandlerInterface $notificationSender,
        FolderSettingsNormalizer $normalizer = null
    ) {
        $this->data = $data;
        $this->notificationSender = $notificationSender;
        $this->normalizer = $normalizer ??= new FolderSettingsNormalizer();
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->addSelect(['name', 'description', 'settings']);

        if ($this->notificationSender instanceof Scope) {
            $this->notificationSender->apply($builder, $model);
        }
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $updatable): void
    {
        $folder = clone $updatable;
        $newVisibility = FolderVisibility::fromRequest($this->data->visibility);
        $newSettings =  $this->normalizer->fromRequest($this->data->settings);

        if ( ! is_null($newName = $this->data->name)) {
            $folder->name = new FolderName($newName);
        }

        if ($this->data->hasDescription) {
            $folder->description = $this->data->description;
        }

        if ( ! empty($this->data->settings)) {
            $folder->settings = new FolderSettings(array_replace_recursive($folder->settings->toArray(), $newSettings));
        }

        if ( ! is_null($this->data->visibility)) {
            if ($folder->visibility->isPasswordProtected() && ! $newVisibility->isPasswordProtected()) {
                $folder->password = null;
            }

            $folder->visibility = $newVisibility;
        }

        if ( ! is_null($newFolderPassword = $this->data->folderPassword) || $newVisibility->isPasswordProtected()) {
            $folder->password = $newFolderPassword;
        }

        if ( ! $folder->isDirty()) {
            throw new FolderNotModifiedAfterOperationException();
        }

        $updatedFolder = clone $folder;

        $folder->save();

        $this->notificationSender->handle($updatedFolder);
    }
}
