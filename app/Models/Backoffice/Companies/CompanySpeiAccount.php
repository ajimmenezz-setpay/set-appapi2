<?php

namespace App\Models\Backoffice\Companies;

use Illuminate\Database\Eloquent\Model;

class CompanySpeiAccount extends Model
{
    protected $table = 't_backoffice_companies_spei_accounts';

    protected $fillable = [
        'Id',
        'CompanyId',
        'Clabe'
    ];

    public $timestamps = true;
}
