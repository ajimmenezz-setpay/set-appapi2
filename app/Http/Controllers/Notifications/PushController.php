<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CardCloud\CardAssigned;
use App\Models\Users\FirebaseToken;
use App\Http\Controllers\Card\CardManagementController as CardCardManagementController;
use App\Http\Controllers\Notifications\FirebasePushController as FirebaseService;

class PushController extends Controller
{
    public function sendPushNotification(Request $request)
    {
        try {
            $this->validate($request, [
                'card_id' => 'required|integer',
                'title' => 'required|string',
                'body' => 'required|string',
                'movement_type' => 'required|string',
                'description' => 'string',
            ], [
                'card_id.required' => 'El campo card_id es obligatorio.',
                'card_id.integer' => 'El campo card_id debe ser un número entero.',
                'title.required' => 'El campo title es obligatorio.',
                'title.string' => 'El campo title debe ser una cadena de texto.',
                'body.required' => 'El campo body es obligatorio.',
                'body.string' => 'El campo body debe ser una cadena de texto.',
                'movement_type.required' => 'El campo movement_type es obligatorio.',
                'movement_type.string' => 'El campo movement_type debe ser una cadena de texto.',
                'description.string' => 'El campo description debe ser una cadena de texto.',
                'description.required' => 'El campo description es obligatorio.'
            ]);

            $cardId = $request->input('card_id');

            $cardAssigned = CardAssigned::where('CardCloudId', $cardId)->first();
            if ($cardAssigned) {
                $firebaseToken = FirebaseToken::where('UserId', $cardAssigned->UserId)->first();
                if ($firebaseToken) {
                    $pan = CardCardManagementController::cardPan($cardId);
                    $title = "Tarjeta bloqueada";
                    $body = "Su tarjeta con terminación " . substr($pan, -4) . " se ha bloqueado.";
                    $data = ['movementType' => 'CARD_LOCK', 'description' => 'Su tarjeta con terminación ' . substr($pan, -4) . ' ha sido bloqueada por usted o por un administrador. Si cree que esto es un error, contacte a soporte.'];
                    FirebaseService::sendPushNotification($firebaseToken->FirebaseToken, $title, $body, $data);
                }
            }

            return response()->json([
                'message' => 'Notificación push enviada correctamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 200);
        }
    }
}
