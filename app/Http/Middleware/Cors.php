<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Cors
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('OPTIONS')) {
            $response = response('', 204);
        } else {
            try {
                $response = $next($request);
            } catch (\Exception $e) {
                \Log::error('CORS caught exception: ' . $e->getMessage());
                $response = response()->json(['error' => $e->getMessage()], 500);
            }
        }

        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-XSRF-TOKEN');
        $response->headers->set('Access-Control-Max-Age', '3600');

        \Log::info('CORS middleware applied to: ' . $request->fullUrl() . ' | status: ' . $response->getStatusCode() . ' | Origin header: ' . $response->headers->get('Access-Control-Allow-Origin'));

        return $response;
    }
}
