<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MfaProgressStatus implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $authUser;
    public string $jobId;
    public int $rowId;
    public string $status;
    public ?string $message;
    public ?bool $isDeleted;

    /**
     * Create a new event instance.
     */
    public function __construct($authUser, string $jobId, int $rowId, string $status, ?bool $isDeleted = false, ?string $message = null)
    {
        $this->authUser = $authUser;
        $this->jobId = $jobId;
        $this->rowId = $rowId;
        $this->status = $status;
        $this->message = $message;
        $this->isDeleted = $isDeleted;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn()
    {
        return new Channel("user.{$this->authUser->id}.mfa");
    }

    /**
     * The name of the event for the frontend.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'progress';
    }
}
