<?php

namespace App\Http\Services;

use App\Http\Controllers\Security\Crypt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Exception\RequestException;

class CardCloudApi
{
    public static function getToken($user_id)
    {
        try {
            $user = User::where('Id', $user_id)->first();

            $credentials = DB::table('t_stp_card_cloud_credentials')
                ->where('BusinessId', $user->BusinessId)
                ->first();

            if (!$credentials) {
                throw new \Exception('Card Cloud credentials not found');
            }

            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', env('CARD_CLOUD_BASE_URL') . '/api/auth/login', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'email' => Crypt::decrypt($credentials->User),
                    'password' => Crypt::decrypt($credentials->Password),
                ]),

            ]);

            if($response->getStatusCode() != 200) {
                return null;
            }

            $response = json_decode($response->getBody()->getContents());

            return $response->access_token;
        } catch (RequestException $e) {
            return null;
        }
    }
}
