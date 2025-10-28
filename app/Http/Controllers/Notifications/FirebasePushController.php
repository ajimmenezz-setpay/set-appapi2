<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\Users\FirebaseToken;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FirebasePushController extends Controller
{
    public function asociateDeviceToken(Request $request)
    {
        try {
            $this->validate($request, [
                'firebase_token' => 'required|string|max:512',
            ]);

            FirebaseToken::updateOrCreate(
                [
                    'UserId' => $request->attributes->get('jwt')->id,
                    'FirebaseToken' => $request->input('firebase_token')
                ],
                []
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
                Log::error('❌ Error al enviar la notificación push');
                Log::error('Respuesta: ' . $response->body());
            }else{
                Log::info('✅ Notificación push enviada correctamente');
                Log::info('Respuesta: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('⚠️ Error al enviar la notificación push: ' . $e->getMessage());
        }
    }
}
