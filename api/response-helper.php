<?php

if (!function_exists('apiJsonResponse')) {
    function apiJsonResponse(int $code, array $payload): void
    {
        http_response_code($code);
        echo json_encode($payload);
        exit;
    }
}

if (!function_exists('apiBusinessError')) {
    function apiBusinessError(string $message, int $code = 400, array $extra = []): void
    {
        apiJsonResponse($code, array_merge([
            'status' => false,
            'message' => $message,
        ], $extra));
    }
}

if (!function_exists('apiServerError')) {
    function apiServerError(Throwable $exception, string $context, string $publicMessage = 'Internal server error', int $code = 500, array $extra = []): void
    {
        error_log('[' . $context . '] ' . $exception->getMessage());
        apiJsonResponse($code, array_merge([
            'status' => false,
            'message' => $publicMessage,
        ], $extra));
    }
}
