<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Illuminate\Contracts\Support\Arrayable;
use App\Attributes\CheckDataTransferObjectForDefaultProperties;

#[CheckDataTransferObjectForDefaultProperties]
abstract class DataTransferObject implements Arrayable
{
    /**
     * @var array<string, mixed> $attributes
     */
    protected array $attributes;

    public function __construct()
    {
        (new Validator\ExecuteAfterSetUpClassAttributes($this))->execute();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return $this->attributes;
    }

    public function has(string $attribute): bool
    {
        return array_key_exists($attribute, $this->attributes);
    }

    /**
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function __set($key, $value)
    {
        if ($this->has($key)) {
            throw new \Exception(
                sprintf('Cannot change %s attribute for %s', $key, static::class),
                4040
            );
        }

        $this->attributes[$key] = $value;
    }
}
