<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Response;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        Response::macro('api', function (
            $data = null,
            string $message = 'Success',
            int $code = 200,
            bool $status = true
        ) {
            $meta = [];
            $payloadData = $data;

            // Handle Laravel pagination (Paginator / LengthAwarePaginator)
            if ($data instanceof LengthAwarePaginator || $data instanceof Paginator) {
                $payloadData = $data->items();

                $meta = [
                    'pagination' => [
                        'current_page' => method_exists($data, 'currentPage') ? $data->currentPage() : null,
                        'per_page'     => $data->perPage(),
                        'total'        => $data instanceof LengthAwarePaginator ? $data->total() : null,
                        'last_page'    => $data instanceof LengthAwarePaginator ? $data->lastPage() : null,
                        'from'         => $data->firstItem(),
                        'to'           => $data->lastItem(),
                        'has_more'     => method_exists($data, 'hasMorePages') ? $data->hasMorePages() : null,
                    ]
                ];
            }

            // Handle ResourceCollection that wraps a paginator
            if ($data instanceof ResourceCollection && $data->resource instanceof LengthAwarePaginator) {
                $p = $data->resource;
                $payloadData = $data->collection; // already transformed items
                $meta = [
                    'pagination' => [
                        'current_page' => $p->currentPage(),
                        'per_page'     => $p->perPage(),
                        'total'        => $p->total(),
                        'last_page'    => $p->lastPage(),
                        'from'         => $p->firstItem(),
                        'to'           => $p->lastItem(),
                        'has_more'     => $p->hasMorePages(),
                    ]
                ];
            }

            $payload = [
                'status'  => $status,
                'message' => $message,
                'data'    => $payloadData,
            ];

            if (!empty($meta)) {
                $payload['meta'] = $meta;
            }

            return response()->json($payload, $code);
        });

        Response::macro('apiError', function (
            string $message = 'Error',
            array $data = [],
            int $code = 400
        ) {
            $payload = [
                'status'  => false,
                'message' => $message,

            ];

            if (!empty($data)) {
                $payload['data'] = $data;
            }
            return response()->json($payload, $code);
        });
    }
}
