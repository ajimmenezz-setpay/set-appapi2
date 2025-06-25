<?php

namespace App\Models\Backoffice\Companies;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompaniesCommissions extends Model
{
    use HasFactory;

    protected $table = 't_backoffice_companies_commissions';

    protected $fillable = [
        'Id',
        'Type',
        'CompanyId',
        'UpdatedByUser',
        'UpdateDate',
        'CreatedByUser',
        'CreateDate'
    ];
    public $timestamps = false;
}
