<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Enums\Feature;
use App\Exceptions\FolderNotFoundException;
use App\Http\Requests\UpdateCollaboratorActionRequest as Request;
use App\Models\Folder;
use App\Models\FolderDisabledFeature as Model;
use App\Models\FolderFeature;
use App\Repositories\Folder\FeaturesRepository;

final class ToggleFolderFeature
{
    private readonly FeaturesRepository $featuresRepository;

    public function __construct(FeaturesRepository $featuresRepository = null)
    {
        $this->featuresRepository = $featuresRepository ?? new FeaturesRepository();
    }

    public function fromRequest(Request $request): void
    {
        $folderId = $request->integer('folder_id');
        $folder = Folder::query()->find($folderId, ['user_id']);

        [$feature, $action] = $this->mapFeatures($request);

        if (is_null($folder)) {
            throw new FolderNotFoundException();
        }

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        $disabledFeature = Model::query()
            ->where($attributes = ['folder_id' => $folderId, 'feature_id' => $feature->id])
            ->firstOr(fn () => new Model($attributes));

        if ($disabledFeature->exists && $action === 'disable') {
            return;
        }

        if ($action === 'disable') {
            $disabledFeature->save();
        } else {
            $disabledFeature->delete();
        }
    }

    /**
     * @return array{0: FolderFeature, 1: string}
     */
    private function mapFeatures(Request $request): array
    {
        $featureActionMap = [
            Feature::ADD_BOOKMARKS->value    => $request->validated('addBookmarks', null),
            Feature::DELETE_BOOKMARKS->value => $request->validated('removeBookmarks', null),
            Feature::SEND_INVITES->value     => $request->validated('inviteUsers', null),
            Feature::UPDATE_FOLDER->value    => $request->validated('updateFolder', null)
        ];

        $feature = collect($featureActionMap)->filter();

        return [
            $this->featuresRepository->findByName(Feature::from($feature->keys()->sole())),
            $feature->sole()
        ];
    }
}
