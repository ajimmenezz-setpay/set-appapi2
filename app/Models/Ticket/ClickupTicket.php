<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClickupTicket extends Model
{
    use HasFactory;
    protected $table = 'tickets';

    protected $fillable = [
        'ClickupListId',
        'ClickupTaskId',
        'UserId',
        'TicketName',
        'TicketDescription',
        'TicketStatus',
        'StatusColor',
        'MovementId',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';

}
