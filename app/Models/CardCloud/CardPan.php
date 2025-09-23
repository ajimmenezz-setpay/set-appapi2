<?php

namespace App\Models\CardCloud;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CardPan extends Model
{
    use HasFactory;

    protected $connection = 'card_cloud';
    protected $table = 'card_pan';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'CardId',
        'Pan'
    ];

    public $timestamps = false;
}
