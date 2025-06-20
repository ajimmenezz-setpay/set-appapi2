<?php

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Model;

class Additional extends Model
{
    protected $table = 't_users_additional_data';

    protected $primaryKey = 'Id';

    protected $fillable = [
        'UserId',
        'RFC',
        'CURP',
        'VoterCode'
    ];

    public $timestamps = true;


}
