<?php

namespace App\Models\CardCloud;

use Illuminate\Database\Eloquent\Model;

class CardSpeiAccount extends Model
{
    protected $table = 't_card_cloud_spei_accounts';

    protected $fillable = [
        'Id',
        'CardId',
        'Clabe'
    ];

    public $timestamps = true;
}
