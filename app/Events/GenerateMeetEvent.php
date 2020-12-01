<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class GenerateMeetEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $current_week_iteration;

    /**
     * Create a new event instance.
     *
     * @param int $current_week_iteration
     */
    public function __construct($current_week_iteration = 1)
    {
        $this->$current_week_iteration = $current_week_iteration;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
