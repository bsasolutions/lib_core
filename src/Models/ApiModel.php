<?php

namespace Bsa\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletes;

abstract class ApiModel extends Model
{
    use SoftDeletes;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public static function findOrFailApi($id)
    {
        try {
            return static::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return [];
        }
    }
}
