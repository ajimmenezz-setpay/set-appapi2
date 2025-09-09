<?php

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $table = 'cat_profile';

    protected $primaryKey = 'Id';

    protected $fillable = [
        'Name',
        'Level',
        'BusinessId',
        'UrlInit',
        'Active'
    ];

    public $timestamps = false;



}
