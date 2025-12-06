<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ApiLog;

class ApiLogger
{
    public function handle(Request $request, Closure $next)
    {
        // Iniciar cronómetro
        // $start = microtime(true);

        // Procesar request
        $response = $next($request);

        // Detener cronómetro
        // $end = microtime(true);

        // Convertir a milisegundos
        // $executionTime = ($end - $start) * 1000;

        // Obtener info del JWT
        // $jwt = $request->attributes->get('jwt');
        // $userId = $jwt->id ?? null;

        // Guardar log
        // ApiLog::create([
        //     'user_id'           => $userId,
        //     'method'            => $request->method(),
        //     'url'               => $request->fullUrl(),
        //     'ip'                => $request->ip(),
        //     'request_headers'   => json_encode($request->headers->all()),
        //     'request_body'      => json_encode($request->all()),
        //     'response_code'     => $response->getStatusCode(),
        //     'response_body'     => $response->getContent(),
        //     'execution_time_ms' => number_format($executionTime, 2, '.', ''),
        // ]);

        return $response;
    }
}
