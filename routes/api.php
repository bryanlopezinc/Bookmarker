<?php

use App\Http\Controllers;
use App\Http\Controllers\Auth;
use App\Http\Controllers\Folder;
use App\Http\Middleware\ExplodeString as ConvertStringToArray;
use App\Http\Middleware\HandleDbTransactionsMiddleware as DBTransaction;
use App\Http\Middleware\ConfirmPasswordBeforeMakingFolderPublicMiddleware as ConfirmPassword;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\CheckClientCredentials;

Route::middleware('auth:api')->group(function () {

    Route::post('logout', Auth\LogoutController::class)->name('logoutUser');
    Route::get('users/me', Auth\FetchUserProfileController::class)->name('authUserProfile');
    Route::delete('users', Auth\DeleteUserAccountController::class)->name('deleteUserAccount');
    Route::get('users/favourites', Controllers\FetchUserFavouritesController::class)->name('fetchUserFavourites');
    Route::get('users/bookmarks/sources', Controllers\FetchUserBookmarksSourcesController::class)->name('fetchUserSites');
    Route::get('users/tags', Controllers\FetchUserTagsController::class)->name('userTags');
    Route::get('users/tags/search', Controllers\SearchUserTagsController::class)->name('searchUserTags');
    Route::post('emails/add', Controllers\Auth\AddEmailToAccountController::class)->name('addEmailToAccount');
    Route::post('emails/verify/secondary', Auth\VerifySecondaryEmailController::class)->name('verifySecondaryEmail');

    Route::get('users/bookmarks', Controllers\FetchUserBookmarksController::class)
        ->middleware([ConvertStringToArray::keys('tags')])
        ->name('fetchUserBookmarks');

    Route::post('bookmarks/import', Controllers\ImportBookmarkController::class)
        ->middleware([ConvertStringToArray::keys('tags')])
        ->name('importBookmark');

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

        Route::post('folders', Folder\CreateFolderController::class)
            ->middleware(ConvertStringToArray::keys('tags'))
            ->name('createFolder');

        Route::get('folders', Folder\FetchFolderController::class)->name('fetchFolder');
        Route::delete('folders', Folder\DeleteFolderController::class)->name('deleteFolder');
        Route::get('users/folders', Folder\FetchUserFoldersController::class)->name('userFolders');
        Route::get('folders/bookmarks', Folder\FetchFolderBookmarksController::class)->name('folderBookmarks');

        Route::patch('folders', Folder\UpdateFolderController::class)
            ->middleware([ConfirmPassword::class, ConvertStringToArray::keys('tags')])
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

        Route::delete('folders/tags/remove', Controllers\Folder\DeleteFolderTagsController::class)
            ->middleware([ConvertStringToArray::keys('tags')])
            ->name('deleteFolderTags');
    });
}); // End auth middleware

//sendGrid callback url
Route::post('email/save_url', Controllers\SendGrid\Controller::class)->name('saveBookmarkFromEmail');

Route::post('token/refresh', [Laravel\Passport\Http\Controllers\AccessTokenController::class, 'issueToken'])
    ->name('refreshToken')
    ->middleware('throttle');

Route::post('client/oauth/token', Auth\IssueClientTokenController::class)->name('issueClientToken');
Route::post('login', Auth\LoginController::class)->name('loginUser');

Route::middleware([CheckClientCredentials::class])->group(function () {
    Route::post('users', Auth\CreateUserController::class)->middleware([DBTransaction::class])->name('createUser');
    Route::get('folders/public/bookmarks', Folder\FetchPublicFolderBookmarksController::class)->name('viewPublicfolderBookmarks');

    Route::post('users/password/reset-token', Auth\RequestPasswordResetController::class)
        ->middleware([DBTransaction::class])
        ->name('requestPasswordResetToken');

    Route::post('users/password/reset', Auth\ResetPasswordController::class)
        ->middleware([DBTransaction::class])
        ->name('resetPassword');

    Route::post('users/request-verification-code', App\Http\Controllers\Auth\Request2FACodeController::class)
        ->middleware([DBTransaction::class])
        ->name('requestVerificationCode');

    Route::get('email/verify/{id}/{hash}', Auth\VerifyEmailController::class)
        ->middleware('signed')
        ->name('verification.verify');

    Route::post('email/verify/resend', Auth\ResendVerificationLinkController::class)
        ->middleware('throttle:6,1')
        ->name('verification.resend');
});
