<?php

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Model;

class UserEnvironments extends Model
{
    protected $table = 't_backoffice_user_environments';

    protected $primaryKey = 'Id';

    protected $fillable = [
        'UserId',
        'EnvironmentId',
    ];
}
