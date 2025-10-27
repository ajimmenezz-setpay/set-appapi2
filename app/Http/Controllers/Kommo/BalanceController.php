<?php

namespace App\Http\Controllers\Kommo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BalanceController extends Controller
{
    public function getBalance(Request $request)
    {
        try {
            $response = Http::post('http://127.0.0.1:3003/api/kommo/salesbot/consulta-saldo', $request->all());

            if ($response->successful()) {
                return response()->json($response->json());
            } else {
                return $this->error('Error al obtener la información');
            }
        } catch (\Exception $e) {
            return $this->error('Error al obtener la información: ' . $e->getMessage());
        }
    }
}
