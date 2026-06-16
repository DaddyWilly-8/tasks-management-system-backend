<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'channel',
        'is_read',
        'is_sent',
        'scheduled_date',
        'scheduled_time',
        'sent_at',
        'read_at',
        'action_url',
        'data',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'is_sent' => 'boolean',
        'scheduled_date' => 'date',
        'scheduled_time' => 'datetime:H:i:s',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
        'data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
