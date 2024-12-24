<?php

namespace App\Http\Services;

use GuzzleHttp\Client;

class ClickupApi
{
    public static function firstRequest($method, $url, $data = [])
    {
        $client = new Client();
        $request = $client->request($method, $url, [
            'headers' => [
                'Authorization' => env('CLICKUP_API_KEY'),
            ]
        ]);
        return $request;
    }
}
