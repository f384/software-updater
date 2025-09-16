<?php

namespace App\Events;

use App\Models\DeploymentTarget;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class DeploymentTargetUpdated implements ShouldBroadcast
{
    use SerializesModels;

    public $target;

    public function __construct(DeploymentTarget $target)
    {
        $this->target = $target;
    }

    public function broadcastOn()
    {
        return new Channel('deployment.' . $this->target->deployment_id);
    }

    public function broadcastWith()
    {
        return [
            'id'       => $this->target->id,
            'hostname' => $this->target->hostname,
            'ip'       => $this->target->ip,
            'status'   => $this->target->status,
            'message'  => $this->target->message,
        ];
    }
}
