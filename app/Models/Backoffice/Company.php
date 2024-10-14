<?php

namespace App\Models\Backoffice;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $table = 't_backoffice_companies';

    protected $fillable = [
        'Id',
        'Folio',
        'Type',
        'BusinessId',
        'CompanyId',
        'FiscalPersonType',
        'FiscalName',
        'TradeName',
        'RFC',
        'PostalAddress',
        'PhoneNumbers',
        'Logo',
        'Slug',
        'Balance',
        'StatusId',
        'RegisterStep',
        'UpdatedByUser',
        'UpdateDate',
        'CreatedByUser',
        'CreateDate',
        'Active'
    ];

    public $timestamps = false;

}
