<?php

use App\Http\Controllers as C;
use App\Http\Controllers\Auth as A;
use App\Http\Controllers\Folder as F;
use App\Http\Middleware\ExplodeString as StringToArray;
use App\Http\Middleware\HandleDbTransactionsMiddleware as DBTransaction;
use App\Http\Middleware\PreventsDuplicateImportMiddleware;
use App\Http\Middleware\PreventsDuplicatePostRequestMiddleware;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\CheckClientCredentials;
use Laravel\Passport\Http\Controllers\AccessTokenController;

Route::middleware(['auth:api', DBTransaction::class])->group(function () {

    Route::prefix('users')->group(function () {
        Route::post('logout', A\LogoutController::class)->name('logoutUser');
        Route::get('me', A\FetchUserProfileController::class)->name('authUserProfile');
        Route::delete('/', A\DeleteUserAccountController::class)->name('deleteUserAccount');
        Route::patch('/', [A\UserController::class, 'update'])->name('updateUser');

        Route::get('folders', F\FetchUserFoldersController::class)->middleware(StringToArray::keys('fields'))->name('userFolders');
        Route::get('folders/collaborations', C\FetchUserCollaborationsController::class)->middleware(StringToArray::keys('fields'))->name('fetchUserCollaborations');
        Route::delete('folders/collaborations/{folder_id}', F\LeaveFolderCollaborationController::class)->name('leaveFolderCollaboration');
        Route::get('folders/collaborators/{collaborator_id}', C\FetchUserFoldersWhereContainsCollaboratorController::class)->middleware(StringToArray::keys('fields'))->name('fetchUserFoldersWhereHasCollaborator');

        Route::post('emails/add', C\AddEmailToAccountController::class)->middleware(PreventsDuplicatePostRequestMiddleware::class)->name('addEmailToAccount');
        Route::delete('emails/remove', C\DeleteEmailController::class)->name('removeEmailFromAccount');
        Route::post('emails/verify/secondary', A\VerifySecondaryEmailController::class)->name('verifySecondaryEmail');

        Route::get('favorites', C\FetchUserFavoritesController::class)->name('fetchUserFavorites');
        Route::post('bookmarks/favorites', C\CreateFavoriteController::class)
            ->middleware([StringToArray::keys('bookmarks'), PreventsDuplicatePostRequestMiddleware::class])
            ->name('createFavorite');
        Route::delete('favorites', C\DeleteFavoriteController::class)->middleware([StringToArray::keys('bookmarks')])->name('deleteFavorite');

        Route::get('bookmarks', C\FetchUserBookmarksController::class)->middleware([StringToArray::keys('tags')])->name('fetchUserBookmarks');
        Route::get('bookmarks/sources', C\FetchUserBookmarksSourcesController::class)->name('fetchUserBookmarksSources');

        Route::get('tags', C\FetchUserTagsController::class)->name('userTags');

        Route::get('notifications', C\FetchUserNotificationsController::class)->name('fetchUserNotifications');
        Route::patch('notifications/read', C\MarkUserNotificationsAsReadController::class)->middleware([StringToArray::keys('ids')])->name('markNotificationsAsRead');

        Route::get('bookmarks/{bookmark_id}/duplicates', C\FetchDuplicateBookmarksController::class)->name('fetchPossibleDuplicates');
        Route::delete('folders/{folder_id}', F\DeleteFolderController::class)->name('deleteFolder');
    });

    Route::prefix('bookmarks')->group(function () {
        Route::post('/', C\CreateBookmarkController::class)->middleware([StringToArray::keys('tags'), PreventsDuplicatePostRequestMiddleware::class])->name('createBookmark');
        Route::delete('/', C\DeleteBookmarkController::class)->middleware([StringToArray::keys('ids')])->name('deleteBookmark');
        Route::patch('/', C\UpdateBookmarkController::class)->middleware([StringToArray::keys('tags')])->name('updateBookmark');
        Route::delete('tags/remove', C\DeleteBookmarkTagsController::class)->middleware([StringToArray::keys('tags')])->name('deleteBookmarkTags');
        Route::post('import', C\ImportBookmarkController::class)
            ->middleware([StringToArray::keys('tags'), PreventsDuplicateImportMiddleware::class])
            ->withoutMiddleware(DBTransaction::class)
            ->name('importBookmark');
    });

    Route::prefix('folders')->group(function () {
        Route::get('/', F\FetchFolderController::class)->middleware([StringToArray::keys('fields')])->name('fetchFolder');
        Route::post('/', F\CreateFolderController::class)->middleware([PreventsDuplicatePostRequestMiddleware::class])->name('createFolder');
        Route::patch('/{folder_id}', F\UpdateFolderController::class)->middleware([StringToArray::keys('tags')])->name('updateFolder');
        Route::get('/{folder_id}/bookmarks', F\FetchFolderBookmarksController::class)->withoutMiddleware('auth:api')->name('folderBookmarks');

        Route::prefix('bookmarks')->group(function () {
            Route::post('/', F\AddBookmarksToFolderController::class)
                ->middleware(StringToArray::keys('bookmarks', 'make_hidden'))
                ->middleware(PreventsDuplicatePostRequestMiddleware::class)
                ->name('addBookmarksToFolder');

            Route::delete('/', F\RemoveBookmarksFromFolderController::class)->middleware(StringToArray::keys('bookmarks'))->name('removeBookmarksFromFolder');

            Route::post('hide', F\HideFolderBookmarksController::class)
                ->middleware(StringToArray::keys('bookmarks'))
                ->middleware(PreventsDuplicatePostRequestMiddleware::class)
                ->name('hideFolderBookmarks');
        });

        Route::patch('collaborators/actions', [F\UpdateFolderController::class, 'updateAction'])->name('updateFolderCollaboratorActions');
        Route::get('/{folder_id}/collaborators', F\FetchFolderCollaboratorsController::class)->middleware(StringToArray::keys('permissions'))->name('fetchFolderCollaborators');
        Route::delete('/{folder_id}/collaborators/{collaborator_id}', F\RemoveCollaboratorController::class)->name('deleteFolderCollaborator');
        Route::delete('/{folder_id}/collaborators/{collaborator_id}/permissions', F\RevokeFolderCollaboratorPermissionsController::class)->middleware([StringToArray::keys('permissions')])->name('revokePermissions');
        Route::patch('/{folder_id}/collaborators/{collaborator_id}/permissions', F\GrantPermissionsToCollaboratorController::class)->middleware([StringToArray::keys('permissions')])->name('grantPermission');
        Route::post('invite', F\SendFolderCollaborationInviteController::class)
            ->middleware([StringToArray::keys('permissions')])
            ->middleware(PreventsDuplicatePostRequestMiddleware::class)
            ->name('sendFolderCollaborationInvite');

        Route::get('/{folder_id}/banned', F\FetchBannedCollaboratorsController::class)->name('fetchBannedCollaborator');
        Route::delete('/{folder_id}/collaborators/{collaborator_id}/ban', F\UnBanUserController::class)->name('unBanUser');

        Route::post('/{folder_id}/collaborators/{collaborator_id}/mute', [F\MuteCollaboratorController::class, 'post'])->middleware(PreventsDuplicatePostRequestMiddleware::class)->name('muteCollaborator');
        Route::delete('/{folder_id}/collaborators/{collaborator_id}/mute', [F\MuteCollaboratorController::class, 'delete'])->name('UnMuteCollaborator');
        Route::get('mute', F\FetchMutedCollaboratorsController::class)->name('fetchMutedCollaborator');
    });

    Route::get('email/verify/{id}/{hash}', A\VerifyEmailController::class)->middleware('signed')->name('verification.verify');
    Route::post('email/verify/resend', A\ResendVerificationLinkController::class)->middleware('throttle:6,1')->name('verification.resend');
}); // End auth middleware

//sendGrid callback url
Route::post('email/save_url', C\SendGrid\Controller::class)->name('saveBookmarkFromEmail');

Route::post('client/oauth/token', A\IssueClientTokenController::class)->name('issueClientToken');
Route::post('login', A\LoginController::class)->name('loginUser');
Route::post('token/refresh', [AccessTokenController::class, 'issueToken'])->name('refreshToken')->middleware('throttle');

Route::middleware([CheckClientCredentials::class])->group(function () {
    Route::get('folders/invite/accept', F\AcceptFolderCollaborationInviteController::class)->middleware([DBTransaction::class])->name('acceptFolderCollaborationInvite');

    Route::post('users', A\UserController::class)
        ->middleware([DBTransaction::class])
        ->middleware(PreventsDuplicatePostRequestMiddleware::class)
        ->name('createUser');

    Route::post('users/password/reset-token', A\RequestPasswordResetController::class)
        ->middleware([DBTransaction::class])
        ->middleware(PreventsDuplicatePostRequestMiddleware::class)
        ->name('requestPasswordResetToken');

    Route::post('users/password/reset', A\ResetPasswordController::class)
        ->middleware([DBTransaction::class])
        ->middleware(PreventsDuplicatePostRequestMiddleware::class)
        ->name('resetPassword');

    Route::post('users/request-verification-code', A\Request2FACodeController::class)
        ->middleware([DBTransaction::class])
        ->middleware(PreventsDuplicatePostRequestMiddleware::class)
        ->name('requestVerificationCode');
});
