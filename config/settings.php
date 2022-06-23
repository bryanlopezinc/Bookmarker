<?php

return [
    //The amount of bookmarks that can be add to  favourites in one request.
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

    //The maximum number of tags a bookmark can have
    'MAX_BOOKMARKS_TAGS' => 15,

    //The amount of time (in minutes) an access token should live
    'ACCESS_TOKEN_EXPIRE' => 60,

    //The amount of time (in minutes) a refresh token should live
    'REFRESH_TOKEN_EXPIRE' => 20_160,

    //The amount of time (in minutes) a verification should live
    'VERIFICATION_CODE_EXPIRE' => 10,

    // frequency (in days) a bookmarks health should be checked.
    'HEALTH_CHECK_FREQUENCY' => 6,
];