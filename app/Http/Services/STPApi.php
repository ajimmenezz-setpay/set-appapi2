<?php

namespace App\Http\Services;

use GuzzleHttp\Exception\RequestException;

class STPApi
{
    public static function collection($url, $key, $company, $date)
    {
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode([
                    'app' => 'getCollectionSTP',
                    'keys' => $key,
                    'company' => $company,
                    'fechaOperacion' => $date,
                ]),
            ]);

            return json_decode($response->getBody()->getContents());
        } catch (RequestException $e) {
            throw new \Exception('Error al obtener la colección. ' . $e->getMessage());
        }
    }
}
