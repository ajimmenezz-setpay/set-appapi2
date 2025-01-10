<?php

namespace App\Models\CardCloud;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CardAssigned extends Model
{
    use HasFactory;
    protected $table = 't_stp_card_cloud_users';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'Id',
        'BusinessId',
        'CardCloudId',
        'CardCloudNumber',
        'UserId',
        'Name',
        'Lastname',
        'Email',
        'IsPending',
        'CreatedByUser',
        'CreateDate'
    ];

    public $timestamps = false;
}
