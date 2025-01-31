<?php

namespace App\Models\CardCloud;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $table = 't_card_cloud_user_contacts';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'UUID',
        'UserId',
        'Alias',
        'ClientId',
        'Active'
    ];
}
