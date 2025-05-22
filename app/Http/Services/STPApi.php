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

    public static function balance($url, $key, $company, $account)
    {
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode([
                    'app' => 'getBalance',
                    'keys' => $key,
                    'company' => $company,
                    'account' => $account
                ]),
            ]);

            return json_decode($response->getBody()->getContents());
        } catch (RequestException $e) {
            throw new \Exception('Error al obtener el balance. ' . $e->getMessage());
        }
    }
}
