<?php

namespace Bsa\Core\Services;

use Bsa\Core\Models\ApiModel;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

abstract class ApiCatalogService
{
    /**
     * Model class used by the catalog.
     *
     * @var class-string<ApiModel>
     */
    protected string $model;

    /**
     * Schema containing the official catalog.
     */
    protected string $catalogSchema = 'public';

    /**
     * Fields whose null local value means inheritance from the public catalog.
     *
     * When empty, fields are inferred from the model fillable attributes.
     *
     * @var array<int, string>
     */
    protected array $catalogFields = [];

    /**
     * Catalog relationships.
     *
     * Example:
     *
     * [
     *     [
     *         'local' => 'adm_states_id',
     *         'public' => 'adm_states_public_id',
     *         'catalog' => 'adm_states_id',
     *         'service' => AdmStateService::class,
     *     ],
     * ]
     *
     * @var array<int, array{
     *     local: string,
     *     public: string,
     *     catalog?: string,
     *     service: class-string<ApiCatalogService>
     * }>
     */
    protected array $catalogRelations = [];

    /**
     * Return all effective catalog records.
     */
    public function all(): Collection
    {
        $catalog = $this->catalogQuery()
            ->get()
            ->map(fn($record) => (array) $record);

        $local = $this->localQuery()
            ->get()
            ->map(fn(ApiModel $record) => $record->toArray());

        $materialized = $local
            ->filter(fn(array $record) => !empty($record['public_id']))
            ->keyBy('public_id');

        $custom = $local
            ->filter(fn(array $record) => empty($record['public_id']));

        $records = $catalog->map(function (array $public) use ($materialized) {
            $local = $materialized->get($public['id']);

            return $this->mergeRecord($public, $local);
        });

        $customRecords = $custom->map(
            fn(array $record) => $this->localRecord($record)
        );

        return $records
            ->concat($customRecords)
            ->values();
    }

    /**
     * Return paginated effective catalog records.
     */
    public function paginate(int $perPage = 50, int $page = 1): LengthAwarePaginator
    {
        $records = $this->all();

        return new LengthAwarePaginator(
            $records->forPage($page, $perPage)->values(),
            $records->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    /**
     * Find an effective record by its public catalog ID.
     */
    public function findByPublicId(int $publicId): array
    {
        $public = $this->findCatalogOrFail($publicId);

        $local = $this->findLocalByPublicId($publicId);

        return $this->mergeRecord(
            $public,
            $local?->toArray()
        );
    }

    /**
     * Find an effective record by its local ID.
     */
    public function findByLocalId(int|string $id): array
    {
        $local = $this->localQuery()
            ->findOrFail($id);

        if (!$local->public_id) {
            return $this->localRecord($local->toArray());
        }

        $public = $this->findCatalogOrFail(
            (int) $local->public_id
        );

        return $this->mergeRecord(
            $public,
            $local->toArray()
        );
    }

    /**
     * Create a custom local record or materialize a public catalog record.
     */
    public function store(array $data): ApiModel
    {
        $publicId = $data['public_id'] ?? null;

        unset($data['public_id']);

        $data = $this->prepareData($data);

        if ($publicId) {
            $model = $this->materialize((int) $publicId);

            if ($data) {
                $model->update($data);
            }

            return $model->refresh();
        }

        return $this->newModel()->newQuery()->create($data);
    }

    /**
     * Update a local catalog record.
     */
    public function update(int|string $id, array $data): ApiModel
    {
        if (array_key_exists('public_id', $data)) {
            throw new InvalidArgumentException(
                'The public_id field cannot be changed after materialization.'
            );
        }

        $model = $this->localQuery()
            ->findOrFail($id);

        $data = $this->prepareData($data);

        $model->update($data);

        return $model->refresh();
    }

    /**
     * Delete a local catalog record.
     */
    public function destroy(int|string $id): ApiModel
    {
        $model = $this->localQuery()
            ->findOrFail($id);

        $model->delete();

        return $model;
    }

    /**
     * Materialize an official catalog record into the current local context.
     */
    public function materialize(int $publicId): ApiModel
    {
        $local = $this->findLocalByPublicId($publicId);

        if ($local) {
            if ($this->usesSoftDeletes() && $local->trashed()) {
                $local->restore();
            }

            return $local;
        }

        $catalog = $this->findCatalogOrFail($publicId);

        $data = [
            'public_id' => $publicId,
        ];

        foreach ($this->relations() as $relation) {
            $catalogField = $relation['catalog'] ?? $relation['local'];
            $relatedPublicId = $catalog[$catalogField] ?? null;

            if ($relatedPublicId === null) {
                continue;
            }

            $service = $this->relationService($relation);

            $related = $service->materialize(
                (int) $relatedPublicId
            );

            $data[$relation['local']] = $related->id;
        }

        $data = array_merge(
            $data,
            $this->materializeData($catalog)
        );

        return $this->newModel()
            ->newQuery()
            ->create($data);
    }

    /**
     * Return the public catalog ID associated with a local ID.
     */
    public function publicIdFromLocalId(int|string|null $id): ?int
    {
        if ($id === null) {
            return null;
        }

        $model = $this->localQueryWithTrashed()
            ->find($id);

        if (!$model || !$model->public_id) {
            return null;
        }

        return (int) $model->public_id;
    }

    /**
     * Prepare API data before persistence.
     *
     * Public relationship IDs are automatically materialized
     * and replaced by their respective local foreign keys.
     */
    public function prepareData(array $data): array
    {
        foreach ($this->relations() as $relation) {
            $publicField = $relation['public'];

            if (!array_key_exists($publicField, $data)) {
                continue;
            }

            $publicId = $data[$publicField];

            unset($data[$publicField]);

            if ($publicId === null || $publicId === '') {
                $data[$relation['local']] = null;

                continue;
            }

            $service = $this->relationService($relation);

            $related = $service->materialize(
                (int) $publicId
            );

            $data[$relation['local']] = $related->id;
        }

        return $this->preparePersistenceData($data);
    }

    /**
     * Merge an official catalog record with its local representation.
     */
    protected function mergeRecord(array $public, ?array $local = null): array
    {
        $result = [
            'id' => $local['id'] ?? null,
            'public_id' => (int) $public['id'],
        ];

        foreach ($this->fields() as $field) {
            $result[$field] =
                $local !== null &&
                array_key_exists($field, $local) &&
                $local[$field] !== null
                ? $local[$field]
                : ($public[$field] ?? null);
        }

        foreach ($this->relations() as $relation) {
            $catalogField = $relation['catalog'] ?? $relation['local'];

            $result[$relation['local']] =
                $local[$relation['local']] ?? null;

            if (
                $local !== null &&
                !empty($local[$relation['local']])
            ) {
                $result[$relation['public']] =
                    $this->relationService($relation)
                    ->publicIdFromLocalId(
                        $local[$relation['local']]
                    );
            } else {
                $value = $public[$catalogField] ?? null;

                $result[$relation['public']] =
                    $value !== null
                    ? (int) $value
                    : null;
            }
        }

        return $this->appendEffectiveData(
            $result,
            $public,
            $local
        );
    }

    /**
     * Normalize a local-only custom record.
     */
    protected function localRecord(array $local): array
    {
        $result = [
            'id' => $local['id'],
            'public_id' => null,
        ];

        foreach ($this->fields() as $field) {
            $result[$field] = $local[$field] ?? null;
        }

        foreach ($this->relations() as $relation) {
            $localId = $local[$relation['local']] ?? null;

            $result[$relation['local']] = $localId;

            $result[$relation['public']] =
                $this->relationService($relation)
                ->publicIdFromLocalId($localId);
        }

        return $this->appendLocalData(
            $result,
            $local
        );
    }

    /**
     * Find an official catalog record.
     */
    protected function findCatalogOrFail(int $publicId): array
    {
        $record = $this->catalogQuery()
            ->where('id', $publicId)
            ->first();

        if (!$record) {
            $exception = new ModelNotFoundException;

            $exception->setModel(
                $this->model,
                [$publicId]
            );

            throw $exception;
        }

        return (array) $record;
    }

    /**
     * Find a local materialized record by its public ID.
     */
    protected function findLocalByPublicId(int $publicId): ?ApiModel
    {
        return $this->localQueryWithTrashed()
            ->where('public_id', $publicId)
            ->first();
    }

    /**
     * Return the official catalog query.
     */
    protected function catalogQuery(): Builder
    {
        $model = $this->newModel();

        return DB::connection($model->getConnectionName())
            ->table($this->catalogTable());
    }

    /**
     * Return the current local query.
     */
    protected function localQuery(): EloquentBuilder
    {
        return $this->newModel()->newQuery();
    }

    /**
     * Return the current local query including deleted records when supported.
     */
    protected function localQueryWithTrashed(): EloquentBuilder
    {
        $query = $this->localQuery();

        if ($this->usesSoftDeletes()) {
            $query->withTrashed();
        }

        return $query;
    }

    /**
     * Return the qualified official catalog table.
     */
    protected function catalogTable(): string
    {
        return $this->catalogSchema . '.' . $this->newModel()->getTable();
    }

    /**
     * Return fields participating in public/local inheritance.
     */
    protected function fields(): array
    {
        if ($this->catalogFields) {
            return $this->catalogFields;
        }

        $excluded = [
            'id',
            'public_id',
            'created_at',
            'updated_at',
            'deleted_at',
            'created_by',
            'updated_by',
            'deleted_by',
        ];

        foreach ($this->relations() as $relation) {
            $excluded[] = $relation['local'];
            $excluded[] = $relation['public'];
        }

        return array_values(
            array_diff(
                $this->newModel()->getFillable(),
                $excluded
            )
        );
    }

    /**
     * Return configured catalog relationships.
     */
    protected function relations(): array
    {
        return $this->catalogRelations;
    }

    /**
     * Resolve a related catalog service.
     */
    protected function relationService(array $relation): ApiCatalogService
    {
        $service = app($relation['service']);

        if (!$service instanceof ApiCatalogService) {
            throw new InvalidArgumentException(
                sprintf(
                    'Catalog relation service [%s] must extend [%s].',
                    $relation['service'],
                    self::class
                )
            );
        }

        return $service;
    }

    /**
     * Return a new model instance.
     */
    protected function newModel(): ApiModel
    {
        if (!isset($this->model)) {
            throw new InvalidArgumentException(
                'The catalog service must define the $model property.'
            );
        }

        $model = app($this->model);

        if (!$model instanceof ApiModel) {
            throw new InvalidArgumentException(
                sprintf(
                    'Catalog model [%s] must extend [%s].',
                    $this->model,
                    ApiModel::class
                )
            );
        }

        return $model;
    }

    /**
     * Determine whether the model uses soft deletes.
     */
    protected function usesSoftDeletes(): bool
    {
        return in_array(
            SoftDeletes::class,
            class_uses_recursive($this->newModel()),
            true
        );
    }

    /**
     * Define additional data required during materialization.
     */
    protected function materializeData(array $catalog): array
    {
        return [];
    }

    /**
     * Perform additional persistence data preparation.
     */
    protected function preparePersistenceData(array $data): array
    {
        return $data;
    }

    /**
     * Append additional data to an effective public/local record.
     */
    protected function appendEffectiveData(
        array $result,
        array $public,
        ?array $local
    ): array {
        return $result;
    }

    /**
     * Append additional data to a local-only record.
     */
    protected function appendLocalData(
        array $result,
        array $local
    ): array {
        return $result;
    }
}
