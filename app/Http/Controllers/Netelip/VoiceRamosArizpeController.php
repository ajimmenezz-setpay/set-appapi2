<?php

namespace App\Http\Controllers\Netelip;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class VoiceRamosArizpeController extends Controller
{
    public function control(Request $request)
    {
        try {
            $response = Http::post('http://127.0.0.1:3002/api/voice-ramos-arizpe/control', $request->all());

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
