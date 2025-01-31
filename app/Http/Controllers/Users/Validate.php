<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class Validate extends Controller
{
    public static function business($user, $businessId) {}

    public static function userProfile($profileId, $userProfileId)
    {
        if(!in_array($userProfileId, $profileId)) {
            throw new \Exception('User not allowed to access this resource');
        }
    }
}
