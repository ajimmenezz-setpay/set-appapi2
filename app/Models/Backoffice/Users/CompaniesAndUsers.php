<?php

namespace App\Models\Backoffice\Users;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompaniesAndUsers extends Model
{
    use HasFactory;
    protected $table = 't_backoffice_companies_and_users';

    protected $fillable = [
        'CompanyId',
        'UserId',
        'ProfileId',
        'Name',
        'Lastname',
        'Email',
        'CreateDate'
    ];

    public $timestamps = false;
}