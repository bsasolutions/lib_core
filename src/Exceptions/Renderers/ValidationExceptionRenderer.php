<?php

namespace Bsa\Core\Exceptions\Renderers;

use Bsa\Core\Traits\ApiResponseTrait;
use Illuminate\Validation\ValidationException;

class ValidationExceptionRenderer
{
    use ApiResponseTrait;

    public function render(ValidationException $e, $request)
    {
        $errors = $e->errors();
        $fields_array = array_keys($errors);
        $all_fields = implode(', ', $fields_array);
        $firstField = array_key_first($errors);
        $firstMessage = $errors[$firstField][0] ?? '';

        return $this->errorResponse(
            ['core.'.$firstMessage, ['field' => $firstField, 'all_fields' => $all_fields]],
            $e->status,
            ['exception' => class_basename($e)] ?? [],
            $errors
        );
    }
}
