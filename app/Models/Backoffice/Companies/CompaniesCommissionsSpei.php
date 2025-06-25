<?php

namespace App\Models\Backoffice\Companies;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompaniesCommissionsSpei extends Model
{
    use HasFactory;

    protected $table = 't_backoffice_companies_commission_spei';

    protected $fillable = [
        'Id',
        'SpeiOut',
        'SpeiIn',
        'Internal',
        'FeeStp',
        'StpAccount'
    ];
    public $timestamps = false;
}
