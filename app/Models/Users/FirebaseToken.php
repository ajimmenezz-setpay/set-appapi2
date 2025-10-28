<?php

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Model;

class FirebaseToken extends Model
{
    protected $table = 't_backoffice_user_firebase_token';
    protected $primaryKey = 'Id';
    public $timestamps = true;

    protected $fillable = [
        'UserId',
        'FirebaseToken',
    ];
}
