<?php

use App\Http\Controllers;
use App\Http\Middleware\ConvertNestedValuesToArrayMiddleware as ConvertStringToArray;
use App\Http\Middleware\HandleDbTransactionsMiddleware as DBTransaction;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\CheckClientCredentials;

Route::middleware('auth:api')->group(function () {

    Route::get('bookmarks', Controllers\FetchUserBookmarksController::class)->name('fetchUserBookmarks');
    Route::get('users/favourites', Controllers\FetchUserFavouritesController::class)->name('fetchUserFavourites');
    Route::get('users/sites', Controllers\FetchUserSitesController::class)->name('fetchUserSites');
    Route::post('tags/suggest', Controllers\SuggestTagsController::class)->name('suggestTags');

    Route::middleware(DBTransaction::class)->group(function () {
        Route::post('bookmarks', Controllers\CreateBookmarkController::class)
            ->middleware([ConvertStringToArray::keys('tags')])
            ->name('createBookmark');

        Route::delete('bookmarks', Controllers\DeleteBookmarkController::class)
            ->middleware([ConvertStringToArray::keys('ids')])
            ->name('deleteBookmark');

        Route::delete('bookmarks/site', Controllers\DeleteBookmarksFromSiteController::class)->name('deleteBookmarksFromSite');
        Route::post('favourites', Controllers\CreateFavouriteController::class)->name('createFavourite');
        Route::delete('favourites', Controllers\DeleteFavouriteController::class)->name('deleteFavourite');

        Route::delete('bookmarks/tags/remove', Controllers\DeleteBookmarkTagsController::class)
            ->middleware([ConvertStringToArray::keys('tags')])
            ->name('deleteBookmarkTags');

        Route::patch('bookmarks', Controllers\UpdateBookmarkController::class)
            ->middleware([ConvertStringToArray::keys('tags')])
            ->name('updateBookmark');
    });
});

Route::post('client/oauth/token', Controllers\IssueClientTokenController::class)->name('issueClientToken');
Route::post('users', Controllers\CreateUserController::class)->middleware([DBTransaction::class])->name('createUser');
Route::post('login', Controllers\LoginController::class)->name('loginUser');

Route::post('users/password/reset-token', Controllers\RequestPasswordResetController::class)
    ->middleware([DBTransaction::class, CheckClientCredentials::class])
    ->name('requestPasswordResetToken');

Route::post('users/password/reset', Controllers\ResetPasswordController::class)
    ->middleware([DBTransaction::class, CheckClientCredentials::class])
    ->name('resetPassword');

Route::post('users/request-verification-code', App\TwoFA\Controllers\RequestVerificationCodeController::class)
    ->middleware([DBTransaction::class, CheckClientCredentials::class])
    ->name('requestVerificationCode');
