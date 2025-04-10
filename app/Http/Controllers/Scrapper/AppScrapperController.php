<?php

namespace App\Http\Controllers\Scrapper;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AppScrapperController extends Controller
{
    public function google(Request $request, $id)
    {
        try {
            $response = Http::get('http://127.0.0.1:3001/google-play/' . $id);

            if ($response->successful()) {
                return response()->json($response->json());
            } else {
                return $this->error('Error al obtener la información de la app');
            }
        } catch (\Exception $e) {
            return $this->error('Error al obtener la información de la app: ' . $e->getMessage());
        }
    }

    public function apple(Request $request, $id)
    {
        try {
            $response = Http::get('http://127.0.0.1:3001/apple-store/' . $id);

            if ($response->successful()) {
                return response()->json($response->json());
            } else {
                return $this->error('Error al obtener la información de la app');
            }
        } catch (\Exception $e) {
            return $this->error('Error al obtener la información de la app: ' . $e->getMessage());
        }
    }
}
