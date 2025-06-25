<?php

namespace App\Models\Backoffice\Companies;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompaniesServicesCardCloud extends Model
{
    use HasFactory;

    protected $table = 't_backoffice_companies_service_card_cloud';

    protected $fillable = [
        'Id',
        'SubAccountId',
        'SubAccount'
    ];
    public $timestamps = false;
}
