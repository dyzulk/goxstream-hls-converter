<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TranscodingProgress implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $jobId,
        public string $status,
        public float $progress,
        public ?string $error = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("transcoding.{$this->jobId}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return "progress";
    }

    /**
     * Get the data to broadcast.
     *
     * @return array{job_id: string, status: string, progress: float, error: string|null}
     */
    public function broadcastWith(): array
    {
        return [
            "job_id" => $this->jobId,
            "status" => $this->status,
            "progress" => $this->progress,
            "error" => $this->error
        ];
    }
}
