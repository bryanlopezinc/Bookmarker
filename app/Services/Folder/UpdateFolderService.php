<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\DataTransferObjects\FolderSettings;
use App\Enums\FolderVisibility;
use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Http\Requests\CreateOrUpdateFolderRequest as Request;
use App\Models\Folder;
use App\Models\User;
use App\Repositories\Folder\FolderPermissionsRepository;
use Illuminate\Support\Facades\Hash;
use App\Notifications\FolderUpdatedNotification as Notification;
use App\Repositories\NotificationRepository;
use Illuminate\Validation\ValidationException;

final class UpdateFolderService
{
    public function __construct(
        private FetchFolderService $folderRepository,
        private FolderPermissionsRepository $permissions,
        private NotificationRepository $notifications,
    ) {
    }

    /**
     * @throws ValidationException
     * @throws HttpException
     */
    public function fromRequest(Request $request): void
    {
        $folder = $this->folderRepository->find(
            $request->integer('folder'),
            ['id', 'user_id', 'name', 'description', 'visibility', 'settings']
        );

        $authUser = auth('api')->user();

        $this->ensureUserCanUpdateFolder($folder, $request, $authUser->getAuthIdentifier());

        $this->confirmPasswordBeforeUpdatingPrivacy($request, $authUser);

        $this->ensureNoVisibilityConflict($folder, $request);

        $updatedFolder = $this->performUpdate($request, $folder);

        $this->notifyFolderOwner($updatedFolder, $authUser->getAuthIdentifier());
    }

    private function ensureNoVisibilityConflict(Folder $folder, Request $request): void
    {
        if ($request->missing('visibility')) {
            return;
        }

        $exception = HttpException::conflict(['message' => 'DuplicateVisibilityState']);

        $visibility = $request->input('visibility');

        $folderVisibility = FolderVisibility::from($folder->visibility);

        if ($visibility === 'public' && $folderVisibility->isPublic()) {
            throw $exception;
        }

        if ($visibility === 'private' && $folderVisibility->isPrivate()) {
            throw $exception;
        }
    }

    private function ensureUserCanUpdateFolder(Folder $folder, Request $request, int $authUserId): void
    {
        try {
            FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);
        } catch (FolderNotFoundException $e) {
            $userPermissions = $this->permissions->getUserAccessControls($authUserId, $folder->id);

            if ($userPermissions->isEmpty()) {
                throw $e;
            }

            $request->whenHas('visibility', fn () => throw HttpException::forbidden(['message' => 'NoUpdatePrivacyPermission']));

            if (!$userPermissions->canUpdateFolder()) {
                throw HttpException::forbidden(['message' => 'NoUpdatePermission']);
            }
        }
    }

    private function performUpdate(Request $request, Folder $folder): Folder
    {
        if ($request->has('name')) {
            $folder->name = $request->input('name');
        }

        if ($request->has('description')) {
            $folder->description = $request->input('description');
        }

        if ($request->has('visibility')) {
            $folder->visibility = (string) FolderVisibility::fromRequest($request)->value;
        }

        $updatedFolder = clone $folder;

        $folder->save();

        return $updatedFolder;
    }

    private function confirmPasswordBeforeUpdatingPrivacy(Request $request, User $authUser): void
    {
        if ($request->missing('visibility') || $request->input('visibility') === 'private') {
            return;
        }

        if (!Hash::check($request->input('password'), $authUser->getAuthPassword())) {
            throw HttpException::forbidden(['message' => 'InvalidPassword']);
        }
    }

    private function notifyFolderOwner(Folder $folder, int $authUserId): void
    {
        if ($folder->user_id === $authUserId) {
            return;
        }

        $settings = FolderSettings::fromQuery($folder->settings);

        if (
            $settings->notificationsAreDisabled() ||
            $settings->folderUpdatedNotificationIsDisabled()
        ) {
            return;
        }

        $this->notifications->notify(
            $folder->user_id,
            new Notification($folder, $authUserId)
        );
    }
}
