<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Collections\ResourceIDsCollection;
use Illuminate\Http\Request;
use App\Exceptions\InvalidResourceIdException;

class ResourceID
{
    public function __construct(protected readonly int $id)
    {
        $this->validate();
    }

    protected function validate(): void
    {
        throw_if($this->id < 1, new InvalidResourceIdException("invalid " . class_basename($this) . ' ' . $this->id));
    }

    public function toInt(): int
    {
        return $this->id;
    }

    public function toCollection(): ResourceIDsCollection
    {
        return new ResourceIDsCollection([$this]);
    }

    /**
     * @throws \RuntimeException
     */
    public static function fromRequest(Request $request = null, string $key = 'id'): self
    {
        return new self(static::getIdFromRequest($request, $key));
    }

    protected static function getIdFromRequest(Request $request = null, string $key = 'id'): int
    {
        $request = $request ?: request();

        $exception = new \RuntimeException("Could not retrieve resource id with name {$key} from request");

        return (int) $request->input($key, fn () => throw $exception);
    }
}
