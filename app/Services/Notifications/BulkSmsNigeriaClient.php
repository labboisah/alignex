<?php

namespace App\Services\Notifications;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class BulkSmsNigeriaClient
{
    public function send(string $to, string $body): array
    {
        if ($this->dryRun()) {
            return [
                'status' => 'dry_run',
                'provider' => 'bulksms_nigeria',
                'message_id' => null,
                'response' => [
                    'simulated' => true,
                    'to' => $to,
                    'from' => config('services.bulksms_nigeria.sender_id'),
                ],
            ];
        }

        $token = (string) config('services.bulksms_nigeria.api_token');

        if ($token === '') {
            throw new \RuntimeException('BulkSMSNigeria API token is not configured.');
        }

        $payload = [
            'from' => config('services.bulksms_nigeria.sender_id'),
            'to' => $to,
            'body' => $body,
        ];

        $gateway = config('services.bulksms_nigeria.gateway');

        if ($gateway !== null && $gateway !== '') {
            $payload['gateway'] = $gateway;
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->post(rtrim((string) config('services.bulksms_nigeria.base_url'), '/') . '/sms', $payload);

        try {
            $response->throw();
        } catch (RequestException $exception) {
            throw new \RuntimeException($exception->getMessage(), previous: $exception);
        }

        $data = $response->json();

        return [
            'status' => 'sent',
            'provider' => 'bulksms_nigeria',
            'message_id' => data_get($data, 'data.message_id') ?? data_get($data, 'data.id'),
            'response' => $data,
        ];
    }

    private function dryRun(): bool
    {
        return filter_var(config('services.bulksms_nigeria.dry_run'), FILTER_VALIDATE_BOOL)
            || filter_var(config('notifications.dry_run'), FILTER_VALIDATE_BOOL);
    }
}
