<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExamMonitorEvent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $examId,
        public readonly string $type,
        public readonly array $payload,
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("exam-monitor.{$this->examId}");
    }

    public function broadcastAs(): string
    {
        return 'exam.monitor';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'payload' => $this->payload,
            'occurred_at' => now()->toISOString(),
        ];
    }
}
