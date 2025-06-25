<?php

namespace App\Models\Backoffice\Companies;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompaniesServicesSpei extends Model
{
    use HasFactory;

    protected $table = 't_backoffice_companies_service_stp';

    protected $fillable = [
        'Id',
        'StpAccountId',
        'BankAccountId',
        'BankAccountNumber'
    ];
    public $timestamps = false;
}
