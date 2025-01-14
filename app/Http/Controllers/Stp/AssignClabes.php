<?php

namespace App\Http\Controllers\Stp;

use App\Http\Controllers\Controller;
use App\Models\Backoffice\Companies\CompanyProjection;
use App\Models\Backoffice\Companies\CompanySpeiAccount;
use App\Models\CardCloud\CardAssigned;
use App\Models\Speicloud\StpClabe;
use App\Models\CardCloud\CardSpeiAccount;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssignClabes extends Controller
{
    public function fixClabesAssignation()
    {
        $companies = $this->fixCompanieStpAccounts();
        $cards = $this->fixCardStpAccounts();

        return response()->json($companies);
    }

    private function fixCompanieStpAccounts()
    {
        $companies = CompanyProjection::leftJoin('t_backoffice_companies_spei_accounts', 't_backoffice_companies_spei_accounts.CompanyId', '=', 't_backoffice_companies_projection.Id')
            ->whereNull('t_backoffice_companies_spei_accounts.Id')
            ->select('t_backoffice_companies_projection.Id', 't_backoffice_companies_projection.TradeName',)
            ->get();

        $set_account = '1015093b-5d33-404a-a4a4-2c0cff648dda';

        $responseObject = [];

        foreach ($companies as $company) {
            $freeAccount = StpClabe::where('BusinessId', $set_account)
                ->where('Available', 1)
                ->first();

            try {
                DB::beginTransaction();
                CompanySpeiAccount::create([
                    'CompanyId' => $company->Id,
                    'Clabe' => $freeAccount->Number
                ]);

                StpClabe::where('Id', $freeAccount->Id)
                    ->update([
                        'Available' => 0
                    ]);

                DB::commit();

                $responseObject[] = [
                    'Company' => $company->TradeName,
                    'Clabe' => $freeAccount->Number
                ];
            } catch (\Exception $e) {
                DB::rollBack();
                $responseObject[] = [
                    'Company' => $company->TradeName,
                    'Clabe' => $freeAccount->Number,
                    'Error' => $e->getMessage()
                ];

                continue;
            }
        }
    }

    private function fixCardStpAccounts()
    {
        $cards = CardAssigned::leftJoin('t_card_cloud_spei_accounts', 't_card_cloud_spei_accounts.CardId', '=', 't_stp_card_cloud_users.CardCloudId')
            ->whereNull('t_card_cloud_spei_accounts.Id')
            ->select('t_stp_card_cloud_users.CardCloudId')
            ->get();

        $set_account = '1015093b-5d33-404a-a4a4-2c0cff648dda';

        $responseObject = [];

        foreach ($cards as $card) {
            $freeAccount = StpClabe::where('BusinessId', $set_account)
                ->where('Available', 1)
                ->first();

            try {
                $this->assignClabeCardCloud($card->CardCloudId, $freeAccount->Number);

                DB::beginTransaction();
                CardSpeiAccount::create([
                    'CardId' => $card->CardCloudId,
                    'Clabe' => $freeAccount->Number
                ]);

                StpClabe::where('Id', $freeAccount->Id)
                    ->update([
                        'Available' => 0
                    ]);

                DB::commit();

                $responseObject[] = [
                    'Card' => $card->CardCloudId,
                    'Clabe' => $freeAccount->Number
                ];
            } catch (\Exception $e) {
                DB::rollBack();
                $responseObject[] = [
                    'Card' => $card->CardCloudId,
                    'Clabe' => $freeAccount->Number,
                    'Error' => $e->getMessage()
                ];

                continue;
            }
        }
    }

    private function assignClabeCardCloud($uuid, $clabe)
    {
        try {
            $client = new Client();
            $response = $client->request('POST', env('CARD_CLOUD_BASE_URL') . '/dev/assignClabeToCard', [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'uuid' => $uuid,
                    'clabe' => $clabe
                ]
            ]);

            $decodedJson = json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
                $decodedJson = json_decode($responseBody, true);
                $message = 'Error al asignar la clabe.';

                if (json_last_error() === JSON_ERROR_NONE) {
                    $message .= " " . $decodedJson['message'];
                }

                throw new \Exception($message, $statusCode);
            } else {
                throw new \Exception('Error al asignar la clabe.', 500);
            }
        } catch (\Exception $e) {
            throw new \Exception('Error al asignar la clabe.', 500);
        }
    }
}
