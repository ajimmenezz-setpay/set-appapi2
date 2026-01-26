<?php

namespace App\Http\Controllers\CardCloud;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CardCloud\CardAssigned;
use GuzzleHttp\Client;
use App\Http\Services\CardCloudApi;
use App\Models\CardCloud\Card;
use Illuminate\Support\Facades\Log;

class UserCardsController extends Controller
{
    public function index(Request $request)
    {
        try {
            $allowed = true;
            switch ($request->attributes->get('jwt')->profileId) {
                case 8:
                    $allowed = true;
                    break;
                default:
                    $allowed = false;
                    break;
            }

            if (!$allowed) {
                return self::basicError('No tienes permisos para acceder a este recurso.', 403);
            }

            $cards = CardAssigned::where('UserId', $request->attributes->get('jwt')->id)->get();

            $cardList = [];

            foreach ($cards as $card) {
                try {
                    $client = new Client();
                    $response = $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/api/v1/card/' . $card->CardCloudId, [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id, $card->BusinessId),
                        ]
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error fetching card from CardCloud API: ' . $e->getMessage());
                    continue;
                }

                $decodedJson = json_decode($response->getBody(), true);
                $decodedJson['cardNumber'] = $card->CardCloudNumber;
                $decodedJson['email'] = $card->Email;
                $decodedJson['name'] = $card->Name;
                $decodedJson['lastname'] = $card->Lastname;
                $decodedJson['isPending'] = $card->IsPending;
                $decodedJson['ownerId'] = $card->UserId;
                $decodedJson['subAccountId'] = $decodedJson['subaccount_id'] ?? null;
                $decodedJson['companyId'] = $decodedJson['subaccount_id'] ?? null;


                array_push($cardList, $decodedJson);
            }

            return response()->json($cardList, 200);
        } catch (\Exception $e) {
            Log::error('Error fetching user cards: ' . $e->getMessage());
            return self::basicError("No se pudieron obtener las tarjetas del usuario, intente de nuevo más tarde.");
        }
    }
}
