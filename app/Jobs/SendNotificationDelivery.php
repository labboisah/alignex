<?php

namespace App\Jobs;

use App\Models\NotificationDelivery;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendNotificationDelivery implements ShouldQueue, ShouldBeUnique
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $uniqueFor = 300;

    public function __construct(public readonly int $notificationDeliveryId)
    {
        $this->onQueue('notifications');
    }

    public function uniqueId(): string
    {
        return 'notification-delivery:'.$this->notificationDeliveryId;
    }

    public function handle(NotificationDispatcher $dispatcher): void
    {
        $delivery = NotificationDelivery::query()->find($this->notificationDeliveryId);

        if (! $delivery) {
            return;
        }

        $dispatcher->sendPendingDelivery($delivery);
    }
}
