<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentIdentity extends Model
{
    protected $fillable = [
        'agent_id',
        'name',
        'role',
        'capabilities',
        'status',
        'last_active_at',
    ];

    protected $casts = [
        'capabilities' => 'array',
        'last_active_at' => 'datetime',
    ];
}
