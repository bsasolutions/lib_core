<?php

namespace Bsa\Core\Exceptions\Renderers;

use Bsa\Core\Exceptions\ApiException;
use Bsa\Core\Traits\ApiResponseTrait;

class ApiExceptionRenderer
{
    use ApiResponseTrait;

    public function render(ApiException $e, $request)
    {
        return $this->errorResponse(
            [$e->translationKey, $e->params],
            $e->status,
            ['exception' => class_basename($e)] ?? [],
            $e->errors ?? []
        );
    }
}
