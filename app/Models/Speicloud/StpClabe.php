<?php

namespace App\Models\Speicloud;

use Illuminate\Database\Eloquent\Model;

class StpClabe extends Model
{
    protected $table = 't_backoffice_bank_accounts';

    protected $fillable = [
        'Id',
        'BusinessId',
        'Number',
        'Available'
    ];

    public $timestamps = false;
}
