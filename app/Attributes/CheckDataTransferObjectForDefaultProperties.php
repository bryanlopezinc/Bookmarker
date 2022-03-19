<?php

declare(strict_types=1);

namespace App\Attributes;

use Attribute;
use ReflectionClass;
use ReflectionProperty;
use App\Contracts\AfterDTOSetUpHookInterface;

#[Attribute(Attribute::TARGET_CLASS)]
final class CheckDataTransferObjectForDefaultProperties implements AfterDTOSetUpHookInterface
{
    /**
     * @var array<string, bool>
     */
    private static array $checked = [];

    private ReflectionClass $reflection;

    public function executeAfterSetUp(Object $object): void
    {
        if (array_key_exists($object::class, static::$checked)) {
            return;
        }

        $this->reflection = new ReflectionClass($object);

        $this->ensureNoPropertyHasDefaultValue();

        static::$checked[$object::class] = true;
    }

    protected function ensureNoPropertyHasDefaultValue(): void
    {
        $callback = fn (ReflectionProperty $property): bool => $property->hasDefaultValue();

        $objectHasPropertyWithDefaultValue = collect($this->reflection->getProperties())->filter($callback)->isNotEmpty();

        if ($objectHasPropertyWithDefaultValue) {
            throw new \Exception(
                sprintf('%s property/ies cannot have a default values', $this->reflection->getName()),
                5000
            );
        }
    }
}
