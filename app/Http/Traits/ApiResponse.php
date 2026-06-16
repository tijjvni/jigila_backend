<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

trait ApiResponse
{
    protected function okResponse(mixed $data, int $status = 200): JsonResponse
    {
        if ($data instanceof JsonResource || $data instanceof ResourceCollection) {
            return $data->response()->setStatusCode($status);
        }

        return response()->json($data, $status);
    }

    protected function createdResponse(mixed $data): JsonResponse
    {
        return $this->okResponse($data, 201);
    }

    protected function messageResponse(string $message, int $status = 200): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }

    protected function errorResponse(string $message, int $status = 400): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
