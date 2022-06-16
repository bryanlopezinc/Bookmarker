<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Builders;

use App\DataTransferObjects\User;
use App\ValueObjects\Email;
use App\ValueObjects\NonEmptyString;
use App\ValueObjects\UserID;
use App\ValueObjects\Username;
use App\Models\User as Model;
use App\ValueObjects\PositiveNumber;

final class UserBuilder extends Builder
{
    public static function new(): self
    {
        return new self;
    }

    public static function fromModel(Model $model): self
    {
        $attributes = $model->getAttributes();

        $keyExists = fn (string $key) => array_key_exists($key, $attributes);

        return (new self)
            ->when($keyExists('id'), fn (self $sb) => $sb->id($model['id']))
            ->when($keyExists('username'), fn (self $sb) => $sb->username($model['username']))
            ->when($keyExists('firstname'), fn (self $sb) => $sb->firstname($model['firstname']))
            ->when($keyExists('lastname'), fn (self $sb) => $sb->lastname($model['lastname']))
            ->when($keyExists('email'), fn (self $sb) => $sb->email($model['email']))
            ->when($keyExists('bookmarks_count'), fn (self $sb) => $sb->bookmarksCount((int)$model['bookmarks_count']))
            ->when($keyExists('favourites_count'), fn (self $sb) => $sb->favouritesCount((int)$model['favourites_count']))
            ->when($keyExists('folders_count'), fn (self $sb) => $sb->foldersCount((int)$model['folders_count']))
            ->when($keyExists('password'), fn (self $sb) => $sb->password($model['password']));
    }

    public function id(int|UserID $id): self
    {
        $this->attributes['id'] = $id instanceof UserID ? $id : new UserID($id);

        return $this;
    }

    public function username(string $username): self
    {
        $this->attributes['username'] = new Username($username);

        return $this;
    }

    public function firstname(string $firstname): self
    {
        $this->attributes['firstname'] = new NonEmptyString($firstname);

        return $this;
    }

    public function lastname(string $lastname): self
    {
        $this->attributes['lastname'] = new NonEmptyString($lastname);

        return $this;
    }

    public function email(string $email): self
    {
        $this->attributes['email'] = new Email($email);

        return $this;
    }

    public function password(string $password): self
    {
        $this->attributes['password'] = $password;

        return $this;
    }

    public function bookmarksCount(int $count): self
    {
        $this->attributes['bookmarksCount'] = new PositiveNumber($count);

        return $this;
    }

    public function favouritesCount(int $count): self
    {
        $this->attributes['favouritesCount'] = new PositiveNumber($count);

        return $this;
    }

    public function foldersCount(int $count): self
    {
        $this->attributes['foldersCount'] = new PositiveNumber($count);

        return $this;
    }

    public function build(): User
    {
        return new User($this->attributes);
    }
}
