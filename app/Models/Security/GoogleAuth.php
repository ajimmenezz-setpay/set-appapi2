<?php

namespace App\Models\Security;

use Illuminate\Database\Eloquent\Model;

class GoogleAuth extends Model
{
    protected $table = 't_security_authenticator_factors';

    protected $fillable = [
        'Id',
        'UserId',
        'Provider',
        'SecretKey',
        'RecoveryKeys',
        'CreateDate'
    ];

    public $timestamps = false;
}
