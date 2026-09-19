<?php

namespace Bsa\Core\Exceptions\Renderers;

use Bsa\Core\Traits\ApiResponseTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class HttpExceptionRenderer
{
    use ApiResponseTrait;

    public function render(HttpExceptionInterface $e, $request)
    {
        $status = $e->getStatusCode();
        $message = $e->getMessage() ?: (Response::$statusTexts[$status] ?? 'HTTP error');

        return $this->errorResponse($message, $status);
    }
}
