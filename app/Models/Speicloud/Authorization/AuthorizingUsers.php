<?php

namespace App\Models\SpeiCloud\Authorization;

use Illuminate\Database\Eloquent\Model;

class AuthorizingUsers extends Model
{
    protected $table = 't_backoffice_speicloud_authorizing_users';
    protected $primaryKey = 'Id';
    public $timestamps = true;
    protected $fillable = [
        'BusinessId',
        'UserId',
        'CreatedBy',
        'Active'
    ];
}
