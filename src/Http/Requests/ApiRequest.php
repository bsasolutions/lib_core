<?php

namespace Bsa\Core\Http\Requests;

use Bsa\Core\Traits\ApiResponseTrait;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ApiRequest extends FormRequest
{
    use ApiResponseTrait;

    protected function failedValidation(Validator $validator)
    {
        $http = $this->errorResponse('Invalid', 422, [], $validator->errors());
        throw new HttpResponseException(
            $http
        );
    }
}
