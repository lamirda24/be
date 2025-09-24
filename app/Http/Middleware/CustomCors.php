<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CustomCors
{
    public function handle(Request $request, Closure $next)
    {
        // Origin FE produksi kamu
        $origin = 'https://kebijakan-spbe.vercel.app';

        // Preflight (OPTIONS) – balas 204 + header CORS
        if ($request->isMethod('OPTIONS')) {
            return response('', 204)->withHeaders([
                'Access-Control-Allow-Origin'      => $origin,
                'Access-Control-Allow-Methods'     => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers'     => 'Content-Type, Authorization, X-Requested-With, Origin, Accept',
                'Access-Control-Max-Age'           => '86400',
                'Vary'                             => 'Origin',
            ]);
        }

        $response = $next($request);

        // Pakai header bag, aman untuk BinaryFileResponse/StreamedResponse
        $response->headers->set('Access-Control-Allow-Origin',      $origin);
        $response->headers->set('Access-Control-Allow-Methods',     'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers',     'Content-Type, Authorization, X-Requested-With, Origin, Accept');
        $response->headers->set('Access-Control-Max-Age',           '86400');
        $response->headers->set('Vary',                             'Origin');
        // Jika suatu saat butuh cookie (credentials), set true DAN ganti origin dari '*' ke domain spesifik
        // $response->headers->set('Access-Control-Allow-Credentials', 'true');

        return $response;
    }
}
