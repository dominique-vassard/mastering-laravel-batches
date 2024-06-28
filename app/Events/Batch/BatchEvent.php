<?php

namespace App\Events\Batch;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

abstract class BatchEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $batch_id;
    public array $data;

    /**
     * Create a new event instance.
     */
    public function __construct(Batch $batch, array $data = [])
    {
        $this->batch_id = $batch->id;
        $this->data = $data;
    }

    /**
     * Format batch name from the class name
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'batch.' . Str::of(class_basename($this))
            ->remove('Batch')
            ->snake()
            ->toString();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('batch'),
        ];
    }
}
