<?php

namespace App\Helpers;

class ApiResponse
{
  public static function success($data = [], string $message = 'Success', array $meta = []): array
  {
    return [
      'status' => true,
      'message' => $message,
      'data' => $data,
      'meta' => $meta,
    ];
  }

  public static function error(string $message = 'Error', $data = [], int $code = 400, array $meta = []): \Illuminate\Http\JsonResponse
  {
    return response()->json([
      'status' => false,
      'message' => $message,
      'data' => $data,
      'meta' => $meta,
    ], $code);
  }
}
