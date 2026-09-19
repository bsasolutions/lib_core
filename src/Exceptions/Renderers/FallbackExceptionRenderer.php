<?php

namespace Bsa\Core\Exceptions\Renderers;

use Bsa\Core\Traits\ApiResponseTrait;
use Throwable;

class FallbackExceptionRenderer
{
    use ApiResponseTrait;

    public function render(Throwable $e, $request)
    {
        return $this->errorResponse(
            $e->getMessage(),
            500
        );
    }
}
