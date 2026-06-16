<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalenderEvent extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'event_date',
        'type',
        'created_by',
        'background_color',
        'border_color',
        'entity_model',
        'entity_id',
        'details_url',
        'status',
    ];
}
