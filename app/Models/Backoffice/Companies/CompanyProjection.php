<?php

namespace App\Models\Backoffice\Companies;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyProjection extends Model
{
    use HasFactory;

    protected $table = 't_backoffice_companies_projection';

    protected $fillable = [
        'Id',
        'Folio',
        'Type',
        'TypeName',
        'BusinessId',
        'CompanyId',
        'FiscalPersonType',
        'FiscalName',
        'TradeName',
        'Rfc',
        'PostalAddress',
        'PhoneNumbers',
        'Logo',
        'Slug',
        'Balance',
        'StatusId',
        'StatusName',
        'RegisterStep',
        'Users',
        'Services',
        'Documents',
        'Commissions',
        'CostCenters',
        'UpdatedByUser',
        'UpdateDate',
        'CreatedByUser',
        'CreateDate',
        'Active'
    ];

    public $timestamps = false;
}
