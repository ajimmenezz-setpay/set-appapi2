<?php

namespace App\Models\CardCloud;

use Illuminate\Database\Eloquent\Model;

class NipView extends Model
{
    protected $table = 't_auditory_nip_view';
    protected $primaryKey = 'Id';
    public $timestamps = true;
    protected $fillable = ['UserId', 'CardId'];
}
