<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class Modules extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = User::find($request->attributes->get('jwt')->id);

            return response()->json([
                'message' => 'Módulos obtenidos correctamente.',
                'modules' => [] // Reemplaza con los módulos reales del usuario
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }
}
