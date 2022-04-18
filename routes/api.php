<?php

use App\Http\Controllers;
use App\Http\Middleware as MW;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {

    Route::get('bookmarks', Controllers\FetchUserBookmarksController::class)->name('fetchUserBookmarks');
    Route::get('favourites', Controllers\FetchUserFavouritesController::class)->name('fetchUserFavourites');
    Route::get('user/sites', Controllers\FetchUserSitesController::class)->name('fetchUserSites');

    Route::post('bookmarks', Controllers\CreateBookmarkController::class)
        ->middleware([MW\ConvertNestedValuesToArrayMiddleware::keys('tags'), Mw\HandleDbTransactionsMiddleware::class])
        ->name('createBookmark');

    Route::post('users', Controllers\CreateUserController::class)
        ->middleware([Mw\HandleDbTransactionsMiddleware::class])
        ->withoutMiddleware('auth:api')
        ->name('createUser');

    Route::post('users/password/request-token', Controllers\RequestPasswordResetTokenController::class)
        ->middleware([Mw\HandleDbTransactionsMiddleware::class, Laravel\Passport\Http\Middleware\CheckClientCredentials::class])
        ->withoutMiddleware('auth:api')
        ->name('requestPasswordResetToken');

    Route::post('users/password/reset', Controllers\ResetPasswordController::class)
        ->middleware([Mw\HandleDbTransactionsMiddleware::class, Laravel\Passport\Http\Middleware\CheckClientCredentials::class])
        ->withoutMiddleware('auth:api')
        ->name('resetPassword');

    Route::post('login', Controllers\LoginController::class)
        ->withoutMiddleware('auth:api')
        ->name('loginUser');

    Route::delete('bookmarks', Controllers\DeleteBookmarkController::class)
        ->middleware([Mw\HandleDbTransactionsMiddleware::class])
        ->name('deleteBookmark');

    Route::delete('bookmarks/site', Controllers\DeleteBookmarksFromSiteController::class)
        ->middleware([Mw\HandleDbTransactionsMiddleware::class])
        ->name('deleteBookmarksFromSite');

    Route::delete('bookmarks/tags/remove', Controllers\DeleteBookmarkTagsController::class)
        ->middleware([Mw\HandleDbTransactionsMiddleware::class, MW\ConvertNestedValuesToArrayMiddleware::keys('tags')])
        ->name('deleteBookmarkTags');

    Route::patch('bookmarks', Controllers\UpdateBookmarkController::class)
        ->middleware([Mw\HandleDbTransactionsMiddleware::class, MW\ConvertNestedValuesToArrayMiddleware::keys('tags')])
        ->name('updateBookmark');

    Route::post('favourites', Controllers\CreateFavouriteController::class)
        ->middleware([Mw\HandleDbTransactionsMiddleware::class])
        ->name('createFavourite');

    Route::delete('favourites', Controllers\DeleteFavouriteController::class)
        ->middleware([Mw\HandleDbTransactionsMiddleware::class])
        ->name('deleteFavourite');
});
