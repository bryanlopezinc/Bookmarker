<?php

use Illuminate\Support\Env;

return [
    //The amount of bookmarks that can be add to  favorites in one request.
    'MAX_POST_FAVOURITES' => 50,

    //The amount of favourites that can be deleted in one request.
    'MAX_DELETE_FAVOURITES' => 50,

    //The amount of bookmarks that can be deleted in one request.
    'MAX_DELETE_BOOKMARKS' => 50,

    //The amount of bookmarks that can be marked as hidden in one request.
    'MAX_POST_HIDE_BOOKMARKS' => 50,

    //The amount of bookmarks that can be removed from a folder in one request.
    'MAX_DELETE_FOLDER_BOOKMARKS' => 50,

    //The amount of bookmarks that can be added to a folder in one request.
    'MAX_POST_FOLDER_BOOKMARKS' => 50,

    //The maximum number of data that can be requested perPage when
    //retrieving user bookmarks sources.
    'PER_PAGE_BOOKMARKS_SOURCES' => 50,

    //The maximum number of data that can be requested perPage when
    //retrieving user tags.
    'PER_PAGE_USER_TAGS' => 50,

    //The maximum number of result that can be returned from a user tag search request.
    'SEARCH_USER_TAGS_LIMIT' => 50,

    //The amount of time (in minutes) an access token should live
    'ACCESS_TOKEN_EXPIRE' => 60,

    //The amount of time (in minutes) a refresh token should live
    'REFRESH_TOKEN_EXPIRE' => 20_160,

    // frequency (in days) a bookmarks health should be checked.
    'HEALTH_CHECK_FREQUENCY' => 6,

    //The email verification url that will be sent to the user.
    //The url should contain placeholders for the 'id', 'hash', 'signature', and 'expires' parameters.
    //Eg https://webclient.com/:id/:hash?signature=:signature&expires=:expires
    //All extra query parameters will be reserved.
    'EMAIL_VERIFICATION_URL' => Env::getOrFail('EMAIL_VERIFICATION_URL'),

    //The reset password url that will be sent to the users email.
    //The url should contain placeholders for the 'token' and 'email' parameters
    //Eg https://webclient-here.com/?emailQueryName=:email&tokenQueryName=:token
    //All extra query parameters will be reserved.
    'RESET_PASSWORD_URL' => Env::getOrFail('RESET_PASSWORD_URL'),

    //The accept invite url that will be sent to the user.
    //The url should contain placeholders for the 'invite_hash', 'signature', and 'expires' parameters.
    'ACCEPT_INVITE_URL' => Env::getOrFail('ACCEPT_INVITE_URL'),

    'FIRST_NAME_MAX_LENGTH' => 100,
    'LAST_NAME_MAX_LENGTH' => 100,

    //max size (in Kb) for the chrome import file.
    'MAX_CHROME_FILE_SIZE' => 5000,

    //max size (in Kb) for the safari import file.
    'MAX_SAFARI_FILE_SIZE' => 5000,

    //the amount of secondary emails user can add to account.
    'MAX_SECONDARY_EMAIL' => 3,
];
