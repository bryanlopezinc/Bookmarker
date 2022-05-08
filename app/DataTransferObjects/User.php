<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\ValueObjects\Email;
use App\ValueObjects\NonEmptyString;
use App\ValueObjects\PositiveNumber;
use App\ValueObjects\UserID;
use App\ValueObjects\Username;

final class User extends DataTransferObject
{
    public readonly UserID $id;
    public readonly Username $username;
    public readonly NonEmptyString $firstname;
    public readonly NonEmptyString $lastname;
    public readonly Email $email;
    public readonly string $password;
    public readonly PositiveNumber $bookmarksCount;
    public readonly PositiveNumber $favouritesCount;
}