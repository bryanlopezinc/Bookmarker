<?php

declare(strict_types=1);

namespace App\Models\Relations;

use App\Collections\ModelsCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class HasManyThroughJson extends Relation
{
    /**
     * @param array<class-string<Model>,string[]> $relatedKeys The foreign keys names in the json object in "dot" notation.
     * @param array<class-string<Model>,string[]> $select      The columns to select for for the resource ids.
     */
    public function __construct(
        Model $model,
        private readonly array $relatedKeys,
        private readonly array $select,
        private readonly string $jsonAttribute = 'data'
    ) {
        parent::__construct($model->newQuery(), $model);
    }

    /**
     * @inheritdoc
     */
    public function addEagerConstraints(array $models)
    {
        $keys = collect($this->relatedKeys)->map(function (array $relatedKeys) use ($models) {
            return collect($models)
                ->pluck($this->jsonAttribute)
                ->flatMap(fn (array $data) => collect($data)->dot()->only($relatedKeys)->values()->all())
                ->uniqueStrict();
        });

        $this->query->setQuery(
            $this->getEagerConstraintsQuery($keys)
        );
    }

    /**
     * @param Collection<class-string<Model>,mixed> $keys
     */
    private function getEagerConstraintsQuery(Collection $keys): QueryBuilder
    {
        $query = DB::query()->select([]);

        foreach ($keys->keys() as $related) {
            $model = new $related();

            $query->addSelect([
                $related => DB::query()
                    ->selectRaw($this->wrapJsonArrayObject($this->select[$related] ?? []))
                    ->from($model->getTable())
                    ->whereIntegerInRaw($model->getKeyName(), $keys->get($related, []))
            ]);
        }

        return $query;
    }

    /**
     * Wrap the given columns in a JSON_ARRAYAGG(JSON_OBJECT) statement.
     */
    private function wrapJsonArrayObject(array $columns): string
    {
        $columns = collect($columns)
            ->flatMap(fn (string $column) => ["'{$column}'", $column])
            ->implode(',');

        return "JSON_ARRAYAGG(JSON_OBJECT({$columns}))";
    }

    /**
     * @inheritdoc
     */
    public function match(array $models, Collection $results, $relation)
    {
        /** @var array<class-string<Model>,string|null> */
        $result = $results->first(default: $this->parent)->getAttributes();

        $relations = collect($result)
            ->flatMap(function (?string $jsonRecords, string $related) {
                return collect(json_decode($jsonRecords ?? '{}', true, flags: JSON_THROW_ON_ERROR))
                    ->mapInto($related)
                    ->each(fn (Model $model) => $model->exists = true)
                    ->all();
            })
            ->pipeInto(ModelsCollection::class);

        foreach ($models as $model) {
            $model->setRelation($relation, $relations);
        }

        return $models;
    }

    /**
     * @inheritdoc
     */
    public function initRelation(array $models, $relation)
    {
        return $models;
    }

    /**
     * @inheritdoc
     */
    public function addConstraints()
    {
    }

    /**
     * @inheritdoc
     */
    public function getResults()
    {
    }
}
