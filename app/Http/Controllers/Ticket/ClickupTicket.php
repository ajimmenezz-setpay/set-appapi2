<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use App\Http\Services\CardCloudApi;
use Illuminate\Http\Request;
use App\Models\Business\ClickupBusinessList;
use App\Models\Ticket\ClickupTicket as ClickupTicketModel;
use App\Models\Backoffice\Business;
use App\Models\Backoffice\Companies\CompanyProjection;
use Carbon\Carbon;
use Exception;

class ClickupTicket extends Controller
{
    public function create(Request $request)
    {
        $this->validate($request, [
            'description' => 'required|string',
        ]);

        try {
            $dataList = $this->clickupListByBusiness($request->attributes->get('jwt')->businessId);

            if (isset($request->movement_id)) {
                $this->validate($request, [
                    'movement_id' => 'required|string|min:36|max:36',
                ], [
                    'movement_id.required' => 'El ID de movimiento es requerido',
                    'movement_id.string' => 'El ID de movimiento debe ser un UUID',
                    'movement_id.min' => 'El ID de movimiento debe tener al menos 36 caracteres',
                    'movement_id.max' => 'El ID de movimiento debe tener como máximo 36 caracteres',
                ]);

                $movementInfo = self::getMovementInfo($request->movement_id, $request->attributes->get('jwt')->id);

                $date = Carbon::createFromTimestamp($movementInfo->date, 'America/Mexico_City');

                $customFields = [
                    [
                        "id" => "24460b7e-34ab-4ee1-ba15-4221fe308753",
                        "value" => $movementInfo->client_id
                    ],
                    [
                        "id" => "de26e2a8-24a3-456c-9467-a25ab80b42aa",
                        "value" => $date->format('Y-m-d H:i:s')
                    ],
                    [
                        "id" => "e0b56aa8-5eed-446f-b0c1-be96db39d434",
                        "value" => abs($movementInfo->amount)
                    ]
                ];
            }



            $data = [
                'name' => $dataList->TicketPrefix . "-" . str_pad($dataList->TicketNumber, 5, '0', STR_PAD_LEFT),
                'description' => $request->description,
                'assignees' => [
                    $dataList->DefaultAssignee
                ],
                'custom_fields' => array_merge([
                    [
                        'id' => '2ebdaddb-f666-41e4-823a-7abd4ed41a82',
                        'value' => $this->companyNameByUser($request)
                    ],
                    [
                        'id' => '88368b14-d393-46bc-a14e-276b8f8f771e',
                        'value' => $request->attributes->get('jwt')->name
                    ]
                ], $customFields ?? []),
            ];

            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', 'https://api.clickup.com/api/v2/list/' . $dataList->ClickupListId . '/task', [
                'headers' => [
                    'Authorization' => env('CLICKUP_API_KEY'),
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($data),
            ]);

            if ($response->getStatusCode() == 200) {
                $this->updateDataLListTicket($dataList->Id, $dataList->TicketNumber);
                $response = json_decode($response->getBody()->getContents());

                ClickupTicketModel::create([
                    'ClickupListId' => $dataList->ClickupListId,
                    'ClickupTaskId' => $response->id,
                    'UserId' => $request->attributes->get('jwt')->id,
                    'TicketName' => $data['name'],
                    'TicketDescription' => $data['description'],
                    'TicketStatus' => $response->status->status,
                    'StatusColor' => $response->status->color,
                    'MovementId' => $request->movement_id ?? null,
                ]);

                return response()->json($response);
            } else {
                return self::basicError('Error al crear el ticket');
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return self::basicError($e->getMessage());
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }

    private function clickupListByBusiness($businessId)
    {
        $dataList = ClickupBusinessList::where('BusinessId', $businessId)->first();
        if (!$dataList) {
            throw new Exception("Al parecer este ambiente no tiene habilitado el seguimiento de Tickets. Por favor contacte al administrador", 404);
        } else {
            return $dataList;
        }
    }

    private function updateDataLListTicket($id, $ticket)
    {
        $ticket++;
        ClickupBusinessList::where('Id', $id)->update(['TicketNumber' => $ticket]);
    }

    private function companyNameByUser($request)
    {
        $profileId = $request->attributes->get('jwt')->profileId;
        switch ($profileId) {
            case 5:
            case 9:
                $business = Business::where('Id', $request->attributes->get('jwt')->businessId)->first();
                return $business->Name;
                break;
            case 7:
            case 8:
                $company = CompanyProjection::where('Users', 'like', '%' . $request->attributes->get('jwt')->id . '%')->first();
                return $company->TradeName ?? '';
                break;
            default:
                return '';
                break;
        }
    }

    public static function getMovementInfo($movementId, $userId)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/api/v1/card/movement/' . $movementId, [
            'headers' => [
                'Authorization' => 'Bearer ' . CardCloudApi::getToken($userId),
                'Content-Type' => 'application/json',
            ],
        ]);

        if ($response->getStatusCode() == 200) {
            return json_decode($response->getBody()->getContents());
        } else {
            throw new Exception("Error al obtener la información del movimiento", 404);
        }
    }
}
