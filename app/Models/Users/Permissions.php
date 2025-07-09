<?php

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Model;

class Permissions extends Model
{
    protected $table = 'cat_permissions';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'CategoryId',
        'Name',
        'Description',
        'Flag'
    ];

    public $timestamps = true;
}
