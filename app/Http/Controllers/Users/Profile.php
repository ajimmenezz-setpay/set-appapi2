<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Users\Profile as ProfileModel;

class Profile extends Controller
{
    public static function getProfile($user)
    {
        $profile = ProfileModel::where('Id', $user->ProfileId)->first();
        if (!$profile) {
            throw new \Exception('Perfil no encontrado.', 404);
        }
        return $profile;
    }
}
