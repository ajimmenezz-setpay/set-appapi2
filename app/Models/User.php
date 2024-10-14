<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasFactory;

    protected $table = 't_users';

    protected $fillable = [
        'Id',
        'ProfileId',
        'Name',
        'Lastname',
        'Phone',
        'Email',
        'Password',
        'StpAccountId',
        'BusinessId',
        'Register',
        'Active'
    ];

    public $timestamps = false;
    
}
