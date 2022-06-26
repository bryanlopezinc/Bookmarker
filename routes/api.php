<?php

use App\Http\Controllers;
use App\Http\Controllers\Folder;
use App\Http\Middleware\ConvertConcatenatedValuesToArrayMiddleware as ConvertStringToArray;
use App\Http\Middleware\HandleDbTransactionsMiddleware as DBTransaction;
use App\Http\Middleware\ConfirmPasswordBeforeMakingFolderPublicMiddleware as ConfirmPassword;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\CheckClientCredentials;

Route::middleware('auth:api')->group(function () {

    Route::post('logout', Controllers\Auth\LogoutController::class)->name('logoutUser');
    Route::get('users/me', Controllers\Auth\FetchUserProfileController::class)->name('authUserProfile');
    Route::delete('users', Controllers\DeleteUserAccountController::class)->name('deleteUserAccount');
    Route::get('users/favourites', Controllers\FetchUserFavouritesController::class)->name('fetchUserFavourites');
    Route::get('users/bookmarks/sources', Controllers\FetchUserBookmarksSourcesController::class)->name('fetchUserSites');
    Route::get('users/tags', Controllers\FetchUserTagsController::class)->name('userTags');
    Route::get('users/tags/search', Controllers\SearchUserTagsController::class)->name('searchUserTags');

    Route::get('users/bookmarks', Controllers\FetchUserBookmarksController::class)
        ->middleware([ConvertStringToArray::keys('tags')])
        ->name('fetchUserBookmarks');

    Route::middleware(DBTransaction::class)->group(function () {
        Route::post('bookmarks', Controllers\CreateBookmarkController::class)
            ->middleware([ConvertStringToArray::keys('tags')])
            ->name('createBookmark');

        Route::delete('bookmarks', Controllers\DeleteBookmarkController::class)
            ->middleware([ConvertStringToArray::keys('ids')])
            ->name('deleteBookmark');

        Route::post('favourites', Controllers\CreateFavouriteController::class)
            ->middleware([ConvertStringToArray::keys('bookmarks')])
            ->name('createFavourite');

        Route::delete('bookmarks/site', Controllers\DeleteBookmarksFromSiteController::class)->name('deleteBookmarksFromSite');

        Route::delete('favourites', Controllers\DeleteFavouriteController::class)
            ->middleware([ConvertStringToArray::keys('bookmarks')])
            ->name('deleteFavourite');

        Route::delete('bookmarks/tags/remove', Controllers\DeleteBookmarkTagsController::class)
            ->middleware([ConvertStringToArray::keys('tags')])
            ->name('deleteBookmarkTags');

        Route::patch('bookmarks', Controllers\UpdateBookmarkController::class)
            ->middleware([ConvertStringToArray::keys('tags')])
            ->name('updateBookmark');

        Route::post('folders', Folder\CreateFolderController::class)->name('createFolder');
        Route::delete('folders', Folder\DeleteFolderController::class)->name('deleteFolder');
        Route::get('users/folders', Folder\FetchUserFoldersController::class)->name('userFolders');
        Route::get('folders/bookmarks', Folder\FetchFolderBookmarksController::class)->name('folderBookmarks');

        Route::patch('folders', Folder\UpdateFolderController::class)
            ->middleware([ConfirmPassword::class])
            ->name('updateFolder');

        Route::post('bookmarks/folders', Folder\AddBookmarksToFolderController::class)
            ->middleware(ConvertStringToArray::keys('bookmarks', 'make_hidden'))
            ->name('addBookmarksToFolder');

        Route::post('folders/bookmarks/hide', Folder\HideFolderBookmarksController::class)
            ->middleware(ConvertStringToArray::keys('bookmarks'))
            ->name('hideFolderBookmarks');

        Route::delete('bookmarks/folders', Folder\RemoveBookmarksFromFolderController::class)
            ->middleware(ConvertStringToArray::keys('bookmarks'))
            ->name('removeBookmarksFromFolder');

        Route::get('email/verify/{id}/{hash}', Controllers\Auth\VerifyEmailController::class)
            ->middleware('signed')
            ->name('verification.verify');

        Route::post('email/verify/resend', Controllers\Auth\ResendVerificationLinkController::class)
            ->middleware('throttle:6,1')
            ->name('verification.resend');
    });
}); // End auth middleware

Route::post('token/refresh', [Laravel\Passport\Http\Controllers\AccessTokenController::class, 'issueToken'])
    ->name('refreshToken')
    ->middleware('throttle');

Route::post('client/oauth/token', Controllers\Auth\IssueClientTokenController::class)->name('issueClientToken');
Route::post('users', Controllers\CreateUserController::class)->middleware([DBTransaction::class])->name('createUser');
Route::post('login', Controllers\Auth\LoginController::class)->name('loginUser');

Route::get('folders/shared/bookmarks', Folder\FetchSharedFolderBookmarksController::class)
    ->middleware([CheckClientCredentials::class])
    ->name('viewPublicfolderBookmarks');

Route::post('users/password/reset-token', Controllers\Auth\RequestPasswordResetController::class)
    ->middleware([DBTransaction::class, CheckClientCredentials::class])
    ->name('requestPasswordResetToken');

Route::post('users/password/reset', Controllers\Auth\ResetPasswordController::class)
    ->middleware([DBTransaction::class, CheckClientCredentials::class])
    ->name('resetPassword');

Route::post('users/request-verification-code', App\TwoFA\Controllers\RequestVerificationCodeController::class)
    ->middleware([DBTransaction::class, CheckClientCredentials::class])
    ->name('requestVerificationCode');
