<?php

namespace Bsa\Core\Traits;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Lang;

trait ApiResponseTrait
{
    /*
    Field	    Description	        Example
    message	    Message text	   	Invalid or not found
    status	    HTTP code	        200, 201, 400, 404
    meta	    Details	            provider, pagination
    data	    Data 	            data, list, object
    errors	    Errors              errors, details
    */
    public function successResponse(string|array $message, int $status = 200, array $meta = [], mixed $data = [])
    {
        $response = [
            'success' => true,
            'message' => $this->formatMessage($message),
            'meta' => $meta,
            'data' => $data instanceof Arrayable ? $data->toArray() : $data,
        ];
        return response()->json($response, $status, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function errorResponse(string|array $message, int $status = 400, array $meta = [], mixed $errors = [])
    {
        $response = [
            'success' => false,
            'message' => $this->formatMessage($message),
            'meta' => $meta,
            'errors' => $errors instanceof Arrayable ? $errors->toArray() : $errors,
        ];
        return response()->json($response, $status, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function formatMessage(string|array $message): string
    {
        // Note: core.base.welcome -> core/base.welcome
        // Auto generate key: core.base.welcome
        if ($message === 'auto' || (is_array($message) && $message[0] === 'auto')) {
            //return json_encode($message) . ' - ' . $key . ' - ' . json_encode($placeholders);
            $uri    = request()->route()->uri();
            $uri = preg_replace('/\{.*?\}/', '', $uri);
            $uri = trim($uri, '/');
            $method = request()->route()->getActionMethod();
            $key    = str_replace('/', '.', $uri) . '.' . $method;
            $key = preg_replace('/\./', '/', $key, 1);

            $placeholders = is_array($message) ? ($message[1] ?? []) : [];
            if (($placeholders['route'] ?? null) === ':route') {
                $placeholders['route'] = $key;
            }

            // Custom or package or fallback
            if (Lang::has($key)) {
                return __($key, $placeholders);
            }
            if (Lang::has('lib_core::' . $key)) {
                return __('lib_core::' . $key, $placeholders);
            }
            return $key . $this->formatPlaceholdersFallback($placeholders);
        }

        // Array: ['core.base.welcome', ['name' => 'John']]
        if (is_array($message)) {
            $key = $message[0] ?? null;
            $key = preg_replace('/\./', '/', $key, 1);
            $placeholders = $message[1] ?? [];

            // Custom or package or fallback
            if (is_string($key) && Lang::has($key)) {
                return __($key, $placeholders);
            }
            if (is_string($key) && Lang::has('lib_core::' . $key)) {
                return __('lib_core::' . $key, $placeholders);
            }
            return $key ? $key . $this->formatPlaceholdersFallback($placeholders) : json_encode($message, JSON_UNESCAPED_UNICODE);
        }

        // String lang.key: "core.base.welcome"
        if ((str_contains($message, '.')) && (!str_contains($message, ' ')) && (preg_match('/^[a-z0-9._\/-]+$/i', $message))) {
            $key = preg_replace('/\./', '/', $message, 1);

            if (Lang::has($key)) {
                return __($key);
            }
            if (Lang::has('lib_core::' . $key)) {
                return __('lib_core::' . $key);
            }
        }

        // String text: "Welcome John"
        return $message;
    }

    private function formatPlaceholdersFallback(array $placeholders): string
    {
        if (empty($placeholders)) return '';

        $parts = [];
        foreach ($placeholders as $k => $v) {
            $parts[] = "$k: $v";
        }

        return ' (' . implode(', ', $parts) . ')';
    }
}
