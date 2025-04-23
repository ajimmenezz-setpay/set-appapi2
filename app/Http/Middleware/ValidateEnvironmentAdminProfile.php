<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateEnvironmentAdminProfile
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if(!$request->attributes->has('jwt')) {
            return response('El recurso al que intentas acceder no existe o no tienes permisos para acceder a él.', 404);
        }

        if($request->attributes->get('jwt')->profileId !== '5') {
            return response('El recurso al que intentas acceder no existe o no tienes permisos para acceder a él.', 404);
        }

        return $next($request);
    }
}
