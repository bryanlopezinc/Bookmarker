<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'MAX_BOOKMARK_TAGS' => 15,

    //The amount of time (in minutes) an access token should live
    'ACCESS_TOKEN_EXPIRE' => 43_200,

    //The amount of time (in minutes) a refresh token should live
    'REFRESH_TOKEN_EXPIRE' => 525_600,

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

    'MAX_FOLDER_COLLABORATORS_LIMIT' => 1000,
    'MAX_FOLDER_ROLE_NAME' => 64,
    'MAX_ROLES_ATTACHED_TO_INVITES' => 10,
    'MAX_FOLDER_ICON_SIZE' => 2000,

    'MAX_ASSIGN_ROLES_PER_REQUEST' => 10,
    'MAX_SUSPENSION_DURATION_IN_HOURS' => 744,
];
