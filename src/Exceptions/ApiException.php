<?php

namespace Bsa\Core\Exceptions;

use Exception;

class ApiException extends Exception
{
    public string $translationKey;

    public array $params;

    public int $status;

    public array $errors;

    public function __construct(
        array $messageArray,
        int $status = 400,
        array $errors = []
    ) {
        $this->translationKey = (string) ($messageArray[0] ?? '');
        $this->params = (array) ($messageArray[1] ?? []);
        $this->status = $status;
        $this->errors = $errors;

        parent::__construct($this->translationKey, $status);
    }
}
