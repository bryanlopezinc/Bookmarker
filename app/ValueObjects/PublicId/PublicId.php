<?php

declare(strict_types=1);

namespace App\ValueObjects\PublicId;

use App\Casts\PublicIdCast;
use App\Contracts\HasPublicIdInterface;
use App\Contracts\IdGeneratorInterface;
use App\Enums\IdPrefix;
use App\Exceptions\InvalidIdException;
use Illuminate\Contracts\Database\Eloquent\Castable;

abstract class PublicId implements Castable, HasPublicIdInterface
{
    final public function __construct(public readonly string $value)
    {
        /** @var IdGeneratorInterface */
        $validator = app(IdGeneratorInterface::class);

        if ( ! $validator->isValid($value)) {
            throw new InvalidIdException();
        }
    }

    abstract protected static function prefix(): IdPrefix;

    /**
     * @phpstan-return static
     */
    public static function fromRequest(string $id): self
    {
        $id = str($id);

        if ( ! $id->startsWith(static::prefix()->value)) {
            throw new InvalidIdException();
        }

        return new static(
            $id->after(static::prefix()->value)->toString()
        );
    }

    /**
     * @inheritdoc
     */
    public static function castUsing(array $arguments)
    {
        return new PublicIdCast(static::class);
    }

    /**
     * @inheritdoc
     */
    public function getPublicIdentifier(): PublicId
    {
        return $this;
    }

    public function equals(PublicId $publicId): bool
    {
        return $this->value === $publicId->value;
    }

    public function present(): string
    {
        $prefix = static::prefix()->value;

        return "{$prefix}{$this->value}";
    }
}
