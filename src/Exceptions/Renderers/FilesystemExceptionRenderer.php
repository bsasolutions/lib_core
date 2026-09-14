<?php

namespace Bsa\Core\Exceptions\Renderers;

use Throwable;
use Illuminate\Http\Request;

class FilesystemExceptionRenderer
{
    public function render(Throwable $e, Request $request)
    {
        if (
            str_contains($e->getMessage(), 'Failed to open stream') ||
            str_contains($e->getMessage(), 'Permission denied') ||
            str_contains($e->getMessage(), 'could not be opened in append mode') ||
            str_contains($e->getMessage(), 'must be present and writable')
        ) {
            $path = null;

            //if (preg_match('/"([^"]+\.log)"/', $e->getMessage(), $m)) {
            if (preg_match('/"([^"]+)"/', $e->getMessage(), $m)) {
                $path = $m[1];
            }

            return response()->json([
                'success' => false,
                'message' => 'Internal server error. (filesystem-permission)',
                'data' => null,
                'meta' => ['path' => $path],
                'errors' => []
            ], 500);
        }

        return null;
    }
}
