<?php

namespace App\DTO;

class ErrorResponse
{
    public bool $success = false;
    public string $error;
    public ?array $validationErrors;
    public ?string $code;
    public function __construct(
        string $error,
        ?array $validationErrors = null,
        ?string $code = null
    ) {
        $this->error = $error;
        $this->validationErrors = $validationErrors;
        $this->code = $code;
    }
}
?>