<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Builders;

use App\DataTransferObjects\WebSite;
use App\Models\WebSite as Model;
use App\ValueObjects\DomainName;
use App\ValueObjects\NonEmptyString;
use App\ValueObjects\ResourceId;
use App\ValueObjects\TimeStamp;

final class SiteBuilder extends Builder
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
            ->when($keyExists('id'), fn (SiteBuilder $sb) => $sb->id($model['id']))
            ->when($keyExists('host'), fn (SiteBuilder $sb) => $sb->domainName($model['host']))
            ->when($keyExists('name'), fn (SiteBuilder $sb) => $sb->name($model['name']))
            ->when($keyExists('created_at'), fn (SiteBuilder $sb) => $sb->createdAt((string)$model['created_at']))
            ->when($keyExists('name_updated_at'), fn (SiteBuilder $sb) => $sb->nameUpdatedAt((string)$model['name_updated_at']));
    }

    public function id(int $id): self
    {
        $this->attributes['id'] = new ResourceId($id);

        return $this;
    }

    public function createdAt(string $date): self
    {
        $this->attributes['timeCreated'] = new TimeStamp($date);

        return $this;
    }

    public function nameUpdatedAt(?string $date): self
    {
        if (blank($date)) {
            $this->attributes['nameHasBeenUpdated'] = false;

            return $this;
        }

        $this->attributes['nameHasBeenUpdated'] = true;
        $this->attributes['nameUpdatedAt'] = new TimeStamp($date);

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

    public function build(): WebSite
    {
        return new WebSite($this->attributes);
    }
}
