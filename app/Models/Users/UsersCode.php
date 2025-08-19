<?php

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Model;

class UsersCode extends Model
{
    protected $table = 't_users_codes';
    protected $fillable = ['UserId', 'Code', 'Register'];

    public $timestamps = false;
    public $primaryKey = 'Id';
}
