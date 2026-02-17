<?php

namespace App\Presentation\Http\Responses;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function ok(mixed $data = null, ?string $message = null, ?array $meta = null): JsonResponse
    {
        return self::buildResponse($data, 200, $message, $meta);
    }

    public static function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return self::buildResponse($data, 201, $message);
    }

    public static function accepted(mixed $data = null, ?string $message = null): JsonResponse
    {
        return self::buildResponse($data, 202, $message);
    }

    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    private static function buildResponse(mixed $data, int $status, ?string $message = null, ?array $meta = null): JsonResponse
    {
        $payload = ['data' => $data];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }
}
