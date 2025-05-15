<?php

namespace App\Models\CardCloud;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    use HasFactory;

    protected $connection = 'card_cloud';
    protected $table = 'cards';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'UUID',
        'BatchId',
        'CreatorId',
        'SubAccountId',
        'PersonId',
        'CustomerPrefix',
        'CustomerId',
        'Type',
        'ActiveFunction',
        'ExternalId',
        'Brand',
        'MaskedPan',
        'Pan',
        'ExpirationDate',
        'CVV',
        'Pin',
        'Balance',
        'STPAccount',
        'ShowSTPAccount',
        'Substatus'
    ];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
}
