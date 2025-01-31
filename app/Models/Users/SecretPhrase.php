<?php

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Model;

class SecretPhrase extends Model
{
    protected $table = 't_users_secret_phrase';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'UserId',
        'SecretPhrase'
    ];


}
