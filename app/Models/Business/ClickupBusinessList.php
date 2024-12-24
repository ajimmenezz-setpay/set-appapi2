<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClickupBusinessList extends Model
{
    use HasFactory;
    protected $table = 't_backoffice_business_clickup_list';

    protected $fillable = [
        'BusinessId',
        'ClickupListId',
        'TicketPrefix',
        'TicketNumber',
        'DefaultAssignee',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    
}
