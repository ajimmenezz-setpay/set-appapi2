<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CardCloud\CardAssigned;
use App\Models\Users\FirebaseToken;
use App\Http\Controllers\Card\CardManagementController as CardCardManagementController;
use App\Http\Controllers\Notifications\FirebasePushController as FirebaseService;
use App\Models\Notifications\Push as PushModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PushController extends Controller
{
    public function sendPushNotification(Request $request)
    {
        try {
            $this->validate($request, [
                'card_id' => 'required|string',
                'title' => 'required|string',
                'body' => 'required|string',
                'movement_type' => 'required|string',
                'description' => 'string',
            ], [
                'card_id.required' => 'El campo card_id es obligatorio.',
                'card_id.string' => 'El campo card_id debe ser una cadena de texto.',
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

                $bundleContext = DB::table('t_backoffice_business_bundle_context')->where('BusinessId', $cardAssigned->BusinessId)->value('BundleContext');
                if ($bundleContext) {
                    $bundle = $bundleContext;
                }else{
                    $bundle = 'com.set.transaccionales';
                }

                $firebaseToken = FirebaseToken::where('UserId', $cardAssigned->UserId)->first();
                if ($firebaseToken) {
                    PushModel::create([
                        'UserId' => $cardAssigned->UserId,
                        'Token' => $firebaseToken->FirebaseToken,
                        'CardCloudId' => $cardId,
                        'BundleContext' => $bundle,
                        'Title' => $request->input('title'),
                        'Body' => $request->input('body'),
                        'Type' => $request->input('movement_type'),
                        'Description' => $request->input('description'),
                        'IsSent' => false,
                        'IsFailed' => false,
                    ]);
                }
            }

            return response()->json([
                'message' => 'Notificación push guardada para enviar'
            ], 200);
        } catch (\Exception $e) {
            // Log::error('Error sending push notification: ' . $e->getMessage());
            return response()->json([
                'message' => $e->getMessage()
            ], 200);
        }
    }

    public function sendPendingPushNotifications()
    {
        try {
            $pendingNotifications = PushModel::where('IsSent', false)->where('RetryCount', '<', 3)->limit(200)->get();

            foreach ($pendingNotifications as $notification) {


                $sending = FirebaseService::sendPushNotification(
                    $notification->Token,
                    $notification->Title,
                    $notification->Body,
                    [
                        'movementType' => $notification->Type,
                        'description' => $notification->Description
                    ],
                    [
                        'x-bundle-id' => [$notification->BundleContext ?? 'com.set.transaccionales']
                    ]
                );

                if ($sending['status'] === 'success') {
                    $notification->IsSent = true;
                    $notification->SentAt = now();
                    $notification->IsFailed = false;
                    $notification->FailureReason = null;
                    $notification->save();
                } else {
                    $notification->IsFailed = true;
                    $notification->FailureReason = $sending['error'];
                    $notification->RetryCount += 1;
                    $notification->LastRetryAt = now();
                    $notification->save();
                }
            }

            return response()->json([
                'message' => 'Notificaciones push pendientes enviadas'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 200);
        }
    }
}
