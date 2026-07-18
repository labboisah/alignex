<?php

namespace App\Services\Notifications;

use App\Jobs\SendNotificationDelivery;
use App\Models\NotificationDelivery;
use App\Models\NotificationTemplate;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class NotificationDispatcher
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly BulkSmsNigeriaClient $smsClient,
    ) {}

    /**
     * @return array<int, NotificationDelivery>
     */
    public function dispatch(
        string $type,
        array $recipient,
        array $context = [],
        ?array $channels = null,
        ?CarbonInterface $scheduledAt = null,
    ): array {
        $template = $this->resolveTemplate($type);
        $availableChannels = $channels ?? $template['channels'];
        $availableChannels = $this->filterChannelsByPlan($availableChannels, $context);
        $deliveries = [];

        foreach ($availableChannels as $channel) {
            if ($channel === 'email') {
                $deliveries[] = $this->dispatchEmail($type, $template, $recipient, $context, $scheduledAt);
            }

            if ($channel === 'sms') {
                $deliveries[] = $this->dispatchSms($type, $template, $recipient, $context, $scheduledAt);
            }
        }

        return array_values(array_filter($deliveries));
    }

    private function dispatchEmail(
        string $type,
        array $template,
        array $recipient,
        array $context,
        ?CarbonInterface $scheduledAt,
    ): ?NotificationDelivery {
        $email = $recipient['email'] ?? null;

        if ($email === null || $email === '') {
            return null;
        }

        $subject = $this->renderer->render($template['email_subject'] ?? '', $context);
        $body = $this->renderer->render($template['email_body'] ?? '', $context);
        $delivery = $this->createDelivery($type, 'email', $recipient, $subject, $body, $context, $scheduledAt);

        if ($scheduledAt !== null && $scheduledAt->isFuture()) {
            return $delivery;
        }

        $this->queueDelivery($delivery);

        return $delivery;
    }

    /**
     * @return Collection<int, NotificationDelivery>
     */
    public function sendDueScheduled(?int $limit = null): Collection
    {
        $deliveries = NotificationDelivery::query()
            ->where('status', NotificationDelivery::STATUS_PENDING)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->oldest('scheduled_at')
            ->when($limit !== null, fn ($query) => $query->limit($limit))
            ->get();

        return $deliveries->map(function (NotificationDelivery $delivery): NotificationDelivery {
            $this->queueDelivery($delivery);

            return $delivery;
        });
    }

    public function sendPendingDelivery(NotificationDelivery $delivery): NotificationDelivery
    {
        if ($delivery->status !== NotificationDelivery::STATUS_PENDING) {
            return $delivery;
        }

        if ($delivery->channel === 'email') {
            return $this->sendPendingEmailDelivery($delivery);
        }

        if ($delivery->channel === 'sms') {
            return $this->sendPendingSmsDelivery($delivery);
        }

        $delivery->update([
            'status' => NotificationDelivery::STATUS_FAILED,
            'error_message' => 'Unsupported notification channel.',
        ]);

        return $delivery->refresh();
    }

    private function sendPendingEmailDelivery(NotificationDelivery $delivery): NotificationDelivery
    {
        if ($this->dryRun()) {
            $delivery->update([
                'status' => NotificationDelivery::STATUS_DRY_RUN,
                'provider' => 'smtp',
            ]);

            return $delivery->refresh();
        }

        try {
            $this->sendEmail($delivery->recipient_email, $delivery->recipient_name, $delivery->subject ?? '', $delivery->body);

            $delivery->update([
                'status' => NotificationDelivery::STATUS_SENT,
                'provider' => 'smtp',
                'sent_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $delivery->update([
                'status' => NotificationDelivery::STATUS_FAILED,
                'provider' => 'smtp',
                'error_message' => $exception->getMessage(),
            ]);
        }

        return $delivery->refresh();
    }

    private function sendPendingSmsDelivery(NotificationDelivery $delivery): NotificationDelivery
    {
        try {
            $result = $this->smsClient->send($delivery->recipient_phone, $delivery->body);

            $delivery->update([
                'status' => $result['status'] === 'dry_run'
                    ? NotificationDelivery::STATUS_DRY_RUN
                    : NotificationDelivery::STATUS_SENT,
                'provider' => $result['provider'],
                'provider_message_id' => $result['message_id'],
                'payload' => array_merge($delivery->payload ?? [], ['provider_response' => $result['response']]),
                'sent_at' => $result['status'] === 'sent' ? now() : null,
            ]);
        } catch (\Throwable $exception) {
            $delivery->update([
                'status' => NotificationDelivery::STATUS_FAILED,
                'provider' => 'bulksms_nigeria',
                'error_message' => $exception->getMessage(),
            ]);
        }

        return $delivery->refresh();
    }

    private function sendEmail(?string $email, ?string $name, string $subject, string $body): void
    {
        if ($email === null || $email === '') {
            throw new \InvalidArgumentException('Email recipient is missing.');
        }

        Mail::send([
            'html' => 'emails.notifications.professional',
            'text' => 'emails.notifications.professional-text',
        ], [
            'subject' => $subject,
            'body' => $body,
            'isHtml' => $this->isHtml($body),
        ], function ($message) use ($email, $name, $subject): void {
            $message->to($email, $name)->subject($subject);
            });
    }

    private function dispatchSms(
        string $type,
        array $template,
        array $recipient,
        array $context,
        ?CarbonInterface $scheduledAt,
    ): ?NotificationDelivery {
        $phone = $recipient['phone'] ?? null;

        if ($phone === null || $phone === '') {
            return null;
        }

        $body = $this->renderer->render($template['sms_body'] ?? '', $context);
        $delivery = $this->createDelivery($type, 'sms', $recipient, null, $body, $context, $scheduledAt);

        if ($scheduledAt !== null && $scheduledAt->isFuture()) {
            return $delivery;
        }

        $this->queueDelivery($delivery);

        return $delivery;
    }

    private function createDelivery(
        string $type,
        string $channel,
        array $recipient,
        ?string $subject,
        string $body,
        array $context,
        ?CarbonInterface $scheduledAt,
    ): NotificationDelivery {
        return NotificationDelivery::create([
            'type' => $type,
            'channel' => $channel,
            'status' => NotificationDelivery::STATUS_PENDING,
            'recipient_name' => $recipient['name'] ?? null,
            'recipient_email' => $recipient['email'] ?? null,
            'recipient_phone' => $recipient['phone'] ?? null,
            'subject' => $subject,
            'body' => $body,
            'payload' => ['context' => $context],
            'scheduled_at' => $scheduledAt,
        ]);
    }

    private function resolveTemplate(string $type): array
    {
        $databaseTemplate = NotificationTemplate::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->first();

        if ($databaseTemplate !== null) {
            return [
                'channels' => $databaseTemplate->channels,
                'email_subject' => $databaseTemplate->email_subject,
                'email_body' => $databaseTemplate->email_body,
                'sms_body' => $databaseTemplate->sms_body,
            ];
        }

        $template = config("notifications.types.$type");

        if (! is_array($template)) {
            throw new \InvalidArgumentException("Unknown notification type [$type].");
        }

        return $template;
    }

    private function queueDelivery(NotificationDelivery $delivery): void
    {
        SendNotificationDelivery::dispatch($delivery->id)->afterCommit();
    }

    /**
     * @param array<int, string> $channels
     * @return array<int, string>
     */
    private function filterChannelsByPlan(array $channels, array $context): array
    {
        $features = $context['plan_features'] ?? null;

        if (! is_array($features)) {
            return $channels;
        }

        return collect($channels)
            ->filter(fn (string $channel): bool => match ($channel) {
                'email' => (bool) ($features['email_notifications'] ?? false),
                'sms' => (bool) ($features['sms_notifications'] ?? false),
                default => true,
            })
            ->values()
            ->all();
    }

    private function dryRun(): bool
    {
        return filter_var(config('notifications.dry_run'), FILTER_VALIDATE_BOOL);
    }

    private function isHtml(string $body): bool
    {
        return $body !== strip_tags($body);
    }
}
