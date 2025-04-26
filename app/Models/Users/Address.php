<?php

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $table = 't_users_address';
    protected $primaryKey = 'Id';
    public $timestamps = true;
    protected $fillable = [
        'UserId',
        'CountryId',
        'StateId',
        'City',
        'PostalCode',
        'Street',
        'ExternalNumber',
        'InternalNumber',
        'Reference',
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
