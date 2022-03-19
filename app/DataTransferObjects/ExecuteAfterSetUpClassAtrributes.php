<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use ReflectionClass;
use ReflectionAttribute;
use App\Contracts\AfterDTOSetUpHookInterface;

final class ExecuteAfterSetUpClassAtrributes
{
    /**
     * Instances of initialized attributes.
     *
     * Structure {
     *  path/to/class => array<AfterDTOSetUpHookInterface>
     * }
     *
     * @var array<string, array<AfterDTOSetUpHookInterface>>
     */
    private static array $cache = [];

    public function __construct(private Object $object)
    {
        $this->cacheClassAttributes();
    }

    private function cacheClassAttributes(): void
    {
        if (array_key_exists($this->object::class, static::$cache)) {
            return;
        }

        static::$cache[$this->object::class] = $this->getClassAttributesInstances();
    }

    public function execute(): void
    {
        foreach (static::$cache[$this->object::class] as $validator) {
            $validator->executeAfterSetUp($this->object);
        }
    }

    /**
     * @return array<AfterMakingValidatorInterface>
     */
    private function getClassAttributesInstances(): array
    {
        $attrbutes = collect();

        $reflection = new ReflectionClass($this->object);

        while ($reflection !== false) {

            $attrbutes->push(...$reflection->getAttributes(AfterDTOSetUpHookInterface::class, ReflectionAttribute::IS_INSTANCEOF));

            $reflection = $reflection->getParentClass();
        }

        return $attrbutes
            ->reject(fn (?ReflectionAttribute $a): bool => blank($a))
            ->map(fn (ReflectionAttribute $a): AfterDTOSetUpHookInterface => $a->newInstance())
            ->all();
    }
}
