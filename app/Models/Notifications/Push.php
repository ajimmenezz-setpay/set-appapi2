<?php

namespace App\Models\Notifications;

use Illuminate\Database\Eloquent\Model;

class Push extends Model
{
    protected $table = 't_push_notifications';

    protected $primaryKey = 'Id';

    public $timestamps = true;

    protected $fillable = [
        'UserId',
        'Token',
        'CardCloudId',
        'Title',
        'Body',
        'Type',
        'Description',
        'IsSent',
        'SentAt',
        'IsFailed',
        'FailedAt',
        'RetryCount',
        'LastRetryAt',
        'IsRead',
        'ReadAt'
    ];
}
