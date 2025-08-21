<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Model;

class SMTP extends Model
{
    protected $table = 't_backoffice_business_smtp_credentials';

    protected $fillable = [
        'BusinessId',
        'SmtpHost',
        'SmtpPort',
        'SmtpUser',
        'SmtpPassword',
        'SmtpEncryption',
        'SmtpHost2',
        'SmtpPort2',
        'SmtpUser2',
        'SmtpPassword2',
        'SmtpEncryption2',
        'LastUsedMain',
    ];

    protected $primaryKey = 'Id';
}
