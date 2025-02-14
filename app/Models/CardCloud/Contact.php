<?php

namespace App\Models\CardCloud;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $table = 't_card_cloud_user_contacts';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'Id',
        'UUID',
        'UserId',
        'Name',
        'Institution',
        'Account',
        'Alias',
        'ClientId',
        'Active'
    ];
}
