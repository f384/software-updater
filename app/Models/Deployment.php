<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class Deployment extends Model
{
    protected $fillable = [
        'user_id',
        'installer_original_name',
        'installer_stored_path',
        'uninstall_first',
        'options',
        'total_targets',
        'status',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'uninstall_first' => 'boolean',
        'options' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function targets(): HasMany
    {
        return $this->hasMany(DeploymentTarget::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
