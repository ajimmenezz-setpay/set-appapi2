<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class VerifyJwt
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('Authorization');
        $token = str_replace('Bearer ', '', $token);

        try {
            $decoded = JWT::decode($token, new Key(file_get_contents(storage_path(env('JWT_PUBLIC_KEY'))), 'RS256'));
            $validateUser = User::where('Id', $decoded->id)->first();
            if (!$validateUser) {
                return response()->json(['error' => 'User not found'], 401);
            }

            $request->attributes->add(['jwt' => $decoded]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid token' . $e->getMessage()], 401);
        }


        return $next($request);
    }
}
