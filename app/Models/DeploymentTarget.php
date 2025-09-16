<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class DeploymentTarget extends Model
{
    protected $fillable = [
        'deployment_id',
        'hostname',
        'status',
        'progress',
        'log',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
    }

        public function getPasswordAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    // Automatically encrypt when set
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = $value ? Crypt::encryptString($value) : null;
    }
}
