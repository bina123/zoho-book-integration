<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Throwable;

final class ZohoApiException extends Exception
{
    protected int $statusCode;

    protected array $errorData;

    public function __construct(
        string $message = 'Zoho API Error',
        int $statusCode = 500,
        array $errorData = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);

        $this->statusCode = $statusCode;
        $this->errorData = $errorData;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->message,
            'errors' => $this->errorData,
        ], $this->statusCode >= 400 && $this->statusCode < 600 ? $this->statusCode : 500);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorData(): array
    {
        return $this->errorData;
    }
}
