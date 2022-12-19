<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Builders;

use App\DataTransferObjects\Source;
use App\Models\Source as Model;
use App\ValueObjects\DomainName;
use App\ValueObjects\NonEmptyString;
use App\ValueObjects\ResourceID;
use Carbon\Carbon;

final class SourceBuilder extends Builder
{
    public static function new(): self
    {
        return new self();
    }

    public static function fromModel(Model $model): self
    {
        $attributes = $model->getAttributes();

        $keyExists = fn (string $key) => array_key_exists($key, $attributes);

        return (new self())
            ->when($keyExists('id'), fn (SourceBuilder $sb) => $sb->id($model['id']))
            ->when($keyExists('host'), fn (SourceBuilder $sb) => $sb->domainName($model['host']))
            ->when($keyExists('name'), fn (SourceBuilder $sb) => $sb->name($model['name']))
            ->when($keyExists('name_updated_at'), function (SourceBuilder $sb) use ($model) {
                $sb->nameUpdatedAt((string)$model['name_updated_at']);
            });
    }

    public function id(int $id): self
    {
        $this->attributes['id'] = new ResourceID($id);

        return $this;
    }

    public function nameUpdatedAt(?string $date): self
    {
        if (blank($date)) {
            $this->attributes['nameHasBeenUpdated'] = false;

            return $this;
        }

        $this->attributes['nameHasBeenUpdated'] = true;
        $this->attributes['nameUpdatedAt'] = Carbon::parse($date);

        return $this;
    }

    public function name(string $name): self
    {
        $this->attributes['name'] = new NonEmptyString($name);

        return $this;
    }

    public function domainName(string $url): self
    {
        $this->attributes['domainName'] = new DomainName($url);

        return $this;
    }

    public function build(): Source
    {
        return new Source($this->attributes);
    }
}
