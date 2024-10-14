<?php

namespace App\Models\Speicloud;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StpTransaction extends Model
{
    use HasFactory;

    protected $table = 't_spei_transactions';

    protected $fillable = [
        'Id',
        'BusinessId',
        'TypeId',
        'StatusId',
        'Reference',
        'TrackingKey',
        'Concept',
        'SourceAccount',
        'SourceName',
        'SourceBalance',
        'SourceEmail',
        'DestinationAccount',
        'DestinationName',
        'DestinationBalance',
        'DestinationEmail',
        'DestinationBankCode',
        'Amount',
        'Commissions',
        'LiquidationDate',
        'UrlCEP',
        'StpId',
        'ApiData',
        'CreatedByUser',
        'CreateDate',
        'Active'
    ];

    public $timestamps = false;

}
