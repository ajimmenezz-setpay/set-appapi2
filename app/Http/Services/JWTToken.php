<?php

namespace App\Http\Services;

use Firebase\JWT\JWT;

class JWTToken
{
    public static function generateToken($data)
    {
        $payload = [
            'iat' => time(),
            'exp' => time() + (60 * 15), // 15 minutes expiration
        ];
        $payload = array_merge($payload, $data);

        $privateKey = storage_path('app/private/jwt/private.pem');
        $jwt = JWT::encode($payload, file_get_contents($privateKey), 'RS256');
        return $jwt;
    }
}
