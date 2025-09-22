<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Throwable;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $e)
    {
        if ($request->expectsJson()) {
            if ($e instanceof ValidationException) {
                return response()->apiError(
                    'Validation error',
                    ['errors' => $e->errors()],
                    422
                );
            }

            if ($e instanceof ModelNotFoundException) {
                return response()->apiError('Resource not found', [], 404);
            }

            if ($e instanceof AuthenticationException) {
                return response()->apiError('Unauthenticated', [], 401);
            }

            if ($e instanceof AuthorizationException) {
                return response()->apiError('Forbidden', [], 403);
            }
        }

        return parent::render($request, $e);
    }
}
