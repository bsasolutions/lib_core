<?php

namespace Bsa\Core\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\JsonResponse;
use Bsa\Core\Http\Requests\ApiRequest;
use Bsa\Core\Http\Resources\ApiResource;
use Bsa\Core\Models\ApiModel;
use Bsa\Core\Traits\ApiResponseTrait;
use Spatie\QueryBuilder\QueryBuilder;
//use Spatie\QueryBuilder\AllowedFilter;
//use Spatie\QueryBuilder\AllowedSort;

class ApiController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests, ApiResponseTrait;

    public function indexApi(ApiModel $model, ApiResource $resource): JsonResponse
    {
        $perPage = request()->query('per_page', 50);
        $page = request()->query('page', 1);
        //[TODO] Utilizando Spatie abaixo falta personalizar os filtros e ordenações
        $query = QueryBuilder::for(get_class($model))
            ->allowedFilters(['status', 'description']) //[TODO] coloque os campos filtráveis do seu model
            ->allowedSorts(['id', 'created_at']);       //[TODO] coloque os campos ordenáveis
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $meta = [
            'currentPage' => $paginator->currentPage(),
            'totalRecords' => $paginator->total(),
            'totalFiltered' => $paginator->total(),
            'totalPages' => $paginator->lastPage(),
            'totalPerPage' => $perPage,
        ];
        $data = $paginator->items();
        if (count($data) > 0) {
            $data = $resource::collection($data);
            return $this->successResponse('data list', 200, $meta, $data);
        }
        return $this->successResponse('no data', 200);
    }

    public function storeApi(ApiModel $model, ApiRequest $request, ApiResource $resource, ?callable $prepare = null)
    {
        $data = $this->requestData($request);

        if ($prepare) {
            $data = $prepare($data);
        }

        $created = $model::create($data);

        if ($created) {
            $created = new $resource($created);
            return $this->successResponse('created', 201, [], $created);
        }
        return $this->errorResponse('not created', 400);
    }

    public function showApi(ApiModel $model, ApiResource $resource, string $id): JsonResponse
    {
        $found = (new $model)->findOrFailApi($id);
        if ($found) {
            $found = new $resource($found);
            return $this->successResponse('found', 200, [], $found);
        }
        return $this->errorResponse('not found', 400);
    }

    public function updateApi(ApiModel $model, ApiRequest $request, ApiResource $resource, string $id, ?callable $prepare = null): JsonResponse
    {
        $found = (new $model)->findOrFailApi($id);

        $data = $this->requestData($request);

        if ($prepare) {
            $data = $prepare($data);
        }

        $updated = $found->update($data);

        if ($updated) {
            $updated = new $resource($found);
            return $this->successResponse('updated', 200, [], [$updated]);
        }
        return $this->errorResponse('not updated', 400);
    }

    public function destroyApi(ApiModel $model, ApiResource $resource, string $id): JsonResponse
    {
        $found = (new $model)->findOrFailApi($id);

        $deleted = $found->delete();

        if ($deleted) {
            $deleted = new $resource($found);
            return $this->successResponse('deleted', 204, [], [$deleted]);
        }
        return $this->errorResponse('not deleted', 400);
    }

    protected function requestData(ApiRequest $request): array
    {
        return empty($request->rules())
            ? $request->all()
            : $request->validated();
    }
}
