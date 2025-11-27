<?php

namespace App\Models\CardCloud;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subaccount extends Model
{
    use HasFactory;

    protected $connection = 'card_cloud';
    protected $table = 'subaccounts';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'AccountId',
        'UUID',
        'ExternalId',
        'Description',
        'CommissionProfileId',
        'VirtualCardPrice',
        'Active'
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
}
