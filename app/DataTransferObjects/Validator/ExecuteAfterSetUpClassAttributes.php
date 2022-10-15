<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Validator;

use App\Contracts\AfterDTOSetUpHookInterface;

final class ExecuteAfterSetUpClassAttributes
{
    /**
     * Instances of initialized attributes.
     *
     * @var array<class-string, AfterDTOSetUpHookInterface[]>
     */
    private static array $cache = [];

    public function __construct(private Object $object, private Reflector $reflector = new Reflector)
    {
        $this->cacheClassAttributes();
    }

    private function cacheClassAttributes(): void
    {
        if (isset(static::$cache[$this->object::class])) {
            return;
        }

        static::$cache[$this->object::class] = $this->reflector->getClassAttributesInstances($this->object);
    }

    public function execute(): void
    {
        foreach (static::$cache[$this->object::class] as $validator) {
            $validator->executeAfterSetUp($this->object);
        }
    }

    public static function getCache(): array
    {
        return static::$cache;
    }
}
