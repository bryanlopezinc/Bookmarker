<?php

use App\Http\Controllers as C;
use App\Http\Controllers\Auth as A;
use App\Http\Controllers\Folder as F;
use App\Http\Middleware\ExplodeString as StringToArray;
use App\Http\Middleware\HandleDbTransactionsMiddleware as DBTransaction;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\CheckClientCredentials;
use Laravel\Passport\Http\Controllers\AccessTokenController;

Route::middleware(['auth:api', DBTransaction::class])->group(function () {

    Route::prefix('users')->group(function () {
        Route::post('logout', A\LogoutController::class)->name('logoutUser');
        Route::get('me', A\FetchUserProfileController::class)->name('authUserProfile');
        Route::delete('/', A\DeleteUserAccountController::class)->name('deleteUserAccount');

        Route::get('folders', F\FetchUserFoldersController::class)->middleware(StringToArray::keys('fields'))->name('userFolders');
        Route::get('folders/collaborations', C\FetchUserCollaborationsController::class)->middleware(StringToArray::keys('fields'))->name('fetchUserCollaborations');
        Route::delete('folders/collaborations/exit', F\LeaveFolderCollaborationController::class)->name('leaveFolderCollaboration');
        Route::get('folders/contains_collaborator', C\FetchUserFoldersWhereContainsCollaboratorController::class)->middleware(StringToArray::keys('fields'))->name('fetchUserFoldersWhereHasCollaborator');

        Route::post('emails/add', A\AddEmailToAccountController::class)->name('addEmailToAccount');
        Route::delete('emails/remove', A\DeleteEmailController::class)->name('removeEmailFromAccount');
        Route::post('emails/verify/secondary', A\VerifySecondaryEmailController::class)->name('verifySecondaryEmail');

        Route::get('favorites', C\FetchUserFavoritesController::class)->name('fetchUserFavorites');
        Route::post('favorites', C\CreateFavoriteController::class)->middleware([StringToArray::keys('bookmarks')])->name('createFavorite');
        Route::delete('favorites', C\DeleteFavoriteController::class)->middleware([StringToArray::keys('bookmarks')])->name('deleteFavorite');

        Route::get('bookmarks', C\FetchUserBookmarksController::class)->middleware([StringToArray::keys('tags')])->name('fetchUserBookmarks');
        Route::get('bookmarks/sources', C\FetchUserBookmarksSourcesController::class)->name('fetchUserBookmarksSources');

        Route::get('tags', C\FetchUserTagsController::class)->name('userTags');
        Route::get('tags/search', C\SearchUserTagsController::class)->name('searchUserTags');

        Route::get('notifications', C\FetchUserNotificationsController::class)->name('fetchUserNotifications');
        Route::patch('notifications/read', C\MarkUserNotificationsAsReadController::class)->middleware([StringToArray::keys('ids')])->name('markNotificationsAsRead');
    });

    Route::prefix('bookmarks')->group(function () {
        Route::post('/', C\CreateBookmarkController::class)->middleware([StringToArray::keys('tags')])->name('createBookmark');
        Route::delete('/', C\DeleteBookmarkController::class)->middleware([StringToArray::keys('ids')])->name('deleteBookmark');
        Route::get('/duplicates', C\FetchDuplicateBookmarksController::class)->name('fetchPossibleDuplicates');
        Route::patch('/', C\UpdateBookmarkController::class)->middleware([StringToArray::keys('tags')])->name('updateBookmark');
        Route::post('import', C\ImportBookmarkController::class)->middleware([StringToArray::keys('tags')])->withoutMiddleware(DBTransaction::class)->name('importBookmark');
        Route::delete('tags/remove', C\DeleteBookmarkTagsController::class)->middleware([StringToArray::keys('tags')])->name('deleteBookmarkTags');
    });

    Route::prefix('folders')->group(function () {
        Route::get('/', F\FetchFolderController::class)->middleware([StringToArray::keys('fields')])->name('fetchFolder');
        Route::delete('/', F\DeleteFolderController::class)->name('deleteFolder');
        Route::post('/', F\CreateFolderController::class)->name('createFolder');
        Route::patch('/', F\UpdateFolderController::class)->middleware([StringToArray::keys('tags')])->name('updateFolder');

        Route::prefix('bookmarks')->group(function () {
            Route::get('/', F\FetchFolderBookmarksController::class)->withoutMiddleware('auth:api')->name('folderBookmarks');
            Route::post('/', F\AddBookmarksToFolderController::class)->middleware(StringToArray::keys('bookmarks', 'make_hidden'))->name('addBookmarksToFolder');
            Route::delete('/', F\RemoveBookmarksFromFolderController::class)->middleware(StringToArray::keys('bookmarks'))->name('removeBookmarksFromFolder');
            Route::post('hide', F\HideFolderBookmarksController::class)->middleware(StringToArray::keys('bookmarks'))->name('hideFolderBookmarks');
        });

        Route::get('collaborators', F\FetchFolderCollaboratorsController::class)->middleware(StringToArray::keys('permissions'))->name('fetchFolderCollaborators');
        Route::delete('collaborators', F\RemoveCollaboratorController::class)->name('deleteFolderCollaborator');
        Route::delete('collaborators/revoke_permissions', F\RevokeFolderCollaboratorPermissionsController::class)->middleware([StringToArray::keys('permissions')])->name('revokePermissions');
        Route::patch('collaborators/grant', F\GrantPermissionsToCollaboratorController::class)->middleware([StringToArray::keys('permissions')])->name('grantPermission');
        Route::post('invite', F\SendFolderCollaborationInviteController::class)->middleware([StringToArray::keys('permissions')])->name('sendFolderCollaborationInvite');

        Route::get('banned', F\FetchBannedCollaboratorsController::class)->name('fetchBannedCollaborator');
        Route::delete('ban', F\UnBanUserController::class)->name('unBanUser');

        Route::post('mute', F\MuteCollaboratorController::class)->name('muteCollaborator');
        Route::delete('mute', [F\MuteCollaboratorController::class, 'unMute'])->name('UnMuteCollaborator');
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

    Route::post('users', A\CreateUserController::class)->middleware([DBTransaction::class])->name('createUser');
    Route::post('users/password/reset-token', A\RequestPasswordResetController::class)->middleware([DBTransaction::class])->name('requestPasswordResetToken');
    Route::post('users/password/reset', A\ResetPasswordController::class)->middleware([DBTransaction::class])->name('resetPassword');
    Route::post('users/request-verification-code', A\Request2FACodeController::class)->middleware([DBTransaction::class])->name('requestVerificationCode');
});
