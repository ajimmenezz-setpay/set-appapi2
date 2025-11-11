<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\Users\FirebaseToken;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FirebasePushController extends Controller
{
    /**
     * @OA\Post(
     *      path="/api/users/firebase-token/asociate",
     *      summary="Asociar token de dispositivo Firebase al usuario autenticado",
     *      tags={"Firebase Push Notifications"},
     *      description="Asocia el token de dispositivo Firebase al usuario autenticado para recibir notificaciones push.",
     *      operationId="asociateDeviceToken",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"firebase_token"},
     *              @OA\Property(property="firebase_token", type="string", maxLength=512, example="cUTUYpXhTYi397sCPb4MzK:APA91bHcjOxgOLiS5CScaBw4pUf5TMakie-b-aq2wwQ8l0O6-Nqs8aHpq9xL8GeZBh7e5YD35DwxDWpxoEac52ZKU3Vq0DeOVF031XoaBqbPnjsCkEVfEGo")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Token del dispositivo asociado correctamente",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Token del dispositivo asociado correctamente")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error al asociar el token del dispositivo",
     *          @OA\JsonContent(
     *              @OA\Property(property="error", type="string", example="Error al asociar el token del dispositivo: [detalles del error]")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="No autorizado",
     *          @OA\JsonContent(
     *              @OA\Property(property="error", type="string", example="No autorizado")
     *          )
     *     )
     * )
     */
    public function asociateDeviceToken(Request $request)
    {
        try {
            $this->validate($request, [
                'firebase_token' => 'required|string|max:512',
            ]);

            FirebaseToken::updateOrCreate(
                [
                    'UserId' => $request->attributes->get('jwt')->id,
                ],
                [
                    'FirebaseToken' => $request->input('firebase_token')
                ]
            );

            return response()->json(['message' => 'Token del dispositivo asociado correctamente']);
        } catch (\Exception $e) {
            return $this->error('Error al asociar el token del dispositivo: ' . $e->getMessage());
        }
    }

    public static function sendPushNotification($firebaseToken, $title, $body, $data = [])
    {
        $message = [
            'message' => [
                'token' => $firebaseToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $data,
            ],
        ];

        try {
            $response = Http::post('http://127.0.0.1:3003/api/fcm/notifications/send', $message);

            if (!$response->successful()) {
                return [
                    'status' => 'failure',
                    'error' => 'Error al enviar la notificación: ' . $response->body()
                ];
            } else {
                return [
                    'status' => 'success',
                    'response' => $response->json()
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'failure',
                'error' => 'Excepción al enviar la notificación: ' . $e->getMessage()
            ];
        }
    }
}
