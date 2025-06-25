<?php

namespace App\Models\Backoffice\Companies;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompaniesServices extends Model
{
    use HasFactory;

    protected $table = 't_backoffice_companies_services';

    protected $fillable = [
        'Id',
        'Type',
        'CompanyId',
        'UpdateByUser',
        'UpdateDate',
        'CreatedByUser',
        'CreateDate'
    ];
    public $timestamps = false;
}
