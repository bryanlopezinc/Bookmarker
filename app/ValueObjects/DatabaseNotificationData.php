<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Utils\JsonValidator;
use ArrayAccess;
use Illuminate\Support\Arr;

final class DatabaseNotificationData implements ArrayAccess
{
    /**
     * The jsonSchema that will be used to validate the database notification.
     */
    private static string $jsonSchema = '';

    public function __construct(public readonly array $data)
    {
        (new JsonValidator)->validate($this->data, $this->getSchema());
    }

    private function getSchema(): string
    {
        if (!empty(static::$jsonSchema)) {
            return static::$jsonSchema;
        }

        $schema = file_get_contents(base_path('database/JsonSchema/notifications_1.0.0.json'));

        if ($schema === false) {
            throw new \Exception('could not get schema contents');
        }

        return static::$jsonSchema = $schema;
    }

    public function offsetExists($offset): bool
    {
        return Arr::has($this->data, $offset);
    }

    public function offsetGet($offset): mixed
    {
        return Arr::get($this->data, $offset, fn () => throw new \Exception("Invalid offset $offset"));
    }

    public function offsetSet($offset, $value): void
    {
        throw new \Exception(
            sprintf('Cannot set value %s for key %s in %s. Immutable object', $value, $offset, self::class)
        );
    }

    public function offsetUnset($offset): void
    {
        throw new \Exception(
            sprintf('Cannot unset key %s in %s . Immutable object', $offset, self::class)
        );
    }
}
