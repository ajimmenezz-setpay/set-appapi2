<?php

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Model;

class Multiprofile extends Model
{
    protected $table = 'multi_profile_users';

    protected $primaryKey = 'Id';

    protected $fillable = [
        'UserId',
        'ProfileId',
        'IsActive',
    ];
}
