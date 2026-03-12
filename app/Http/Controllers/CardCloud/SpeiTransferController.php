<?php

namespace App\Http\Controllers\CardCloud;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\Security\GoogleAuth;
use App\Http\Controllers\Stp\Transactions\SpeiOut;
use App\Http\Controllers\Stp\Transactions\Transactions;
use App\Models\CardCloud\CardAssigned;
use Ramsey\Uuid\Uuid;
use App\Models\Speicloud\StpTransaction;
use Carbon\Carbon;
use App\Models\Backoffice\Company;
use App\Models\Backoffice\Companies\CompanyProjection;
use Illuminate\Support\Facades\DB;
use App\Services\StpService;
use App\Http\Controllers\Stp\ErrorRegisterOrder;
use GuzzleHttp\Client;

class SpeiTransferController extends Controller
{
    public function transfer(Request $request, $cardId)
    {
        try {
            $this->validate($request, [
                'amount' => 'required|numeric|min:1',
                'destination_account' => 'required|numeric|digits:18',
                'destination_name' => 'required|string|max:100',
                'destination_bank' => 'required|string',
                'description' => 'nullable|string|max:100'
            ], [
                'amount.required' => 'El monto es requerido.',
                'amount.numeric' => 'El monto debe ser un número.',
                'amount.min' => 'El monto debe ser al menos $1.00',
                'destination_account.required' => 'La cuenta de destino es requerida.',
                'destination_account.numeric' => 'La cuenta de destino debe ser una cuenta válida',
                'destination_account.digits' => 'La cuenta de destino debe tener 18 dígitos.',
                'destination_name.required' => 'El nombre del destinatario es requerido.',
                'destination_name.string' => 'El nombre del destinatario debe ser una cadena de texto.',
                'destination_name.max' => 'El nombre del destinatario no puede exceder los 100 caracteres.',
                'destination_bank.required' => 'El banco de destino es requerido.',
                'destination_bank.string' => 'El banco de destino debe ser una cadena de texto.',
                'description.string' => 'La descripción debe ser una cadena de texto.',
                'description.max' => 'La descripción no puede exceder los 100 caracteres.'
            ]);

            if ($request->has('auth_code')) {
                $this->validate($request, [
                    'auth_code' => 'required|min:6|max:6'
                ], [
                    'auth_code.required' => 'El código de autenticación es requerido.',
                    'auth_code.min' => 'El código de autenticación debe tener 6 caracteres.',
                    'auth_code.max' => 'El código de autenticación debe tener 6 caracteres.'
                ]);

                GoogleAuth::authorized($request->attributes->get('jwt')->id, $request->auth_code);
            }

            $this->validateInstitution($request->destination_bank);
            $this->validateCardOwnership($cardId, $request->attributes->get('jwt')->id);

            $speiCompanyAccount = Transactions::searchByCompanyAccount(env('CARD_CLOUD_MAIN_STP_ACCOUNT'));

            $this->validateBalance($speiCompanyAccount['balance'], $request->amount);

            /**
             * AQUI TRANSACCIONAMOS A CARD CLOUD
             */
            $uuid = Uuid::uuid7()->toString();
            $description = 'CardCloud SPEI ' . $cardId . ' a ' . $request->destination_account;
            $origin = [
                'business' => $speiCompanyAccount['business'],
                'stpAccount' => $speiCompanyAccount,
                'account' => $speiCompanyAccount['account'],
                'name' => $speiCompanyAccount['name'],
                'balance' => $speiCompanyAccount['balance'],
            ];

            $destination = [
                'account' => $request->destination_account,
                'name' => $request->destination_name,
                'institution' => $request->destination_bank,
                'description' => $description,
                'amount' => $request->amount
            ];

            $client = new Client();
            $response = $client->request('POST', env('CARD_CLOUD_BASE_URL') . '/authorizations/spei', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'uuid' => $uuid
                ],
                'json' => [
                    'source_card_id' => $cardId,
                    'recipient_account_number' => $request->destination_account,
                    'values' => [
                        'transfer_amount' => $request->amount
                    ]
                ]
            ]);

            if ($response->getStatusCode() != 200) {
                throw new \Exception('No hemos podido procesar la transferencia', 400);
            } else {
                $decodedJson = json_decode($response->getBody(), true);
                if ($decodedJson['response'] != "APPROVED") {
                    throw new \Exception($decodedJson['reason'] ?? "No hemos podido procesar la transferencia", 400);
                }
            }

            $out = $this->createSpeiTransfer($origin, $uuid, $destination, $request);
            $this->updateBalance($speiCompanyAccount['id'], $speiCompanyAccount['balance'] - $destination['amount']);

            $this->processStpRequest($origin, $destination, $out);

            DB::commit();

            return response()->json([
                'message' => 'Transferencia realizada exitosamente.',
                'transaction_id' => $out->Id,
                'stp_id' => $out->StpId
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response($e->getMessage(), 400);
        }
    }

    private function validateInstitution($bankCode)
    {
        $institution = \App\Models\Speicloud\StpInstitutions::where('Code', $bankCode)->where('Active', 1)->first();
        if (!$institution) {
            throw new \Exception('Banco de destino no válido.', 400);
        }
    }

    private function validateCardOwnership($cardId, $userId)
    {
        $cardAssigned = CardAssigned::where('CardCloudId', $cardId)
            ->where('UserId', $userId)
            ->first();
        if (!$cardAssigned) {
            throw new \Exception('No tienes permiso para realizar esta transferencia.', 403);
        }
    }

    private function validateBalance($companyBalance, $amount)
    {
        if ($companyBalance < $amount) {
            throw new \Exception('Fondos insuficientes para realizar la transferencia.', 400);
        }
    }

    private function createSpeiTransfer($origin, $reference, $destination, Request $request)
    {
        $commissions = SpeiOut::calculateOutCommissions('other', $destination['amount']);

        $transaction = new StpTransaction();
        $transaction->Id = Uuid::uuid7()->toString();
        $transaction->BusinessId = $origin['business'];
        $transaction->TypeId = 1;
        $transaction->StatusId = 1;
        $transaction->Reference =  $reference;
        $transaction->TrackingKey = $origin['stpAccount']['stpAccount']['acronym'] . date('YmdHis') . rand(1000, 9999);
        $transaction->Concept = $destination['description'];
        $transaction->SourceAccount = $origin['account'];
        $transaction->SourceName = $origin['name'];
        $transaction->SourceBalance = number_format((float)$origin['balance'], 2, '.', '');
        $transaction->SourceEmail = "";
        $transaction->DestinationAccount = $destination['account'];
        $transaction->DestinationName = $destination['name'];
        $transaction->DestinationBalance = 0;
        $transaction->DestinationEmail = "";
        $transaction->DestinationBankCode = $destination['institution'];
        $transaction->Amount = $destination['amount'];
        $transaction->Commissions = json_encode($commissions);
        $transaction->LiquidationDate = null;
        $transaction->UrlCEP = "";
        $transaction->StpId = 0;
        $transaction->ApiData = '[]';
        $transaction->CreatedByUser = $request->attributes->get('jwt')->id;
        $transaction->CreateDate = Carbon::now(new \DateTimeZone('America/Mexico_City'))->format('Y-m-d H:i:s');
        $transaction->Active = 1;
        $transaction->save();

        return $transaction;
    }

    private function updateBalance($companyId, $balance)
    {
        Company::where('Id', $companyId)->update([
            'Balance' => $balance
        ]);

        CompanyProjection::where('Id', $companyId)->update([
            'Balance' => $balance
        ]);
    }

    private function processStpRequest($origin, $destination, $out)
    {
        if (env('APP_ENV') == 'production') {
            $response = StpService::speiOut(
                $origin['stpAccount']['stpAccount']['url'],
                $origin['stpAccount']['stpAccount']['key'],
                $origin['stpAccount']['stpAccount']['company'],
                $destination['amount'],
                $out->TrackingKey,
                substr(preg_replace('/[^a-zA-Z0-9\s]/', '', $destination['description']), 0, 38),
                $origin['stpAccount']['stpAccount']['number'],
                $origin['stpAccount']['name'],
                "",
                $destination['account'],
                $destination['name'],
                $out->Reference,
                90646,
                "",
                40,
                $destination['institution'],
                40
            );

            if (isset($response->respuesta->id) && count($response->respuesta->id) > 3) {
                $stpId = $response->respuesta->id;
            } else {
                throw new \Exception("No hemos podido procesar la transferencia:" . ErrorRegisterOrder::error($response->respuesta->id));
            }
        } else {
            $stpId = rand(100000, 200000);
        }

        StpTransaction::where('Id', $out->Id)->update([
            'StpId' => $stpId
        ]);
    }
}
