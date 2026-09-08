<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class AdaptiveEngineClient
{
    public const VERSION = 'shadow-2pl-eap-v1';

    public function evaluate(array $payload): array
    {
        $url = (string) config('adaptive.engine.url');
        $secret = (string) config('adaptive.engine.secret');
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (! config('adaptive.engine.shadow_enabled') || strlen($secret) < 32
            || ! ($scheme === 'https' || ($scheme === 'http' && in_array($host, ['127.0.0.1', 'localhost', '[::1]'], true)))
            || parse_url($url, PHP_URL_USER) || parse_url($url, PHP_URL_QUERY)) {
            throw new \RuntimeException('engine_unconfigured');
        }
        try {
            $response = Http::acceptJson()->withHeaders(['X-AlignEx-Engine-Key' => $secret])
                ->connectTimeout(1)->timeout(5)->withoutRedirecting()
                ->post(rtrim($url, '/').'/v1/evaluate', $payload);
            if (! $response->successful() || strlen($response->body()) > 16000) {
                throw new \RuntimeException('engine_unavailable');
            }
            $data = $response->json();
        } catch (ConnectionException) {
            throw new \RuntimeException('engine_unavailable');
        }
        $validator = Validator::make(is_array($data) ? $data : [], [
            'protocol' => ['required', 'in:alignex-shadow-v1'],
            'engine_version' => ['required', 'in:'.self::VERSION],
            'request_id' => ['required', 'in:'.$payload['request_id']],
            'state_version' => ['required', 'integer', 'in:'.$payload['state_version']],
            'calibration_fingerprint' => ['required', 'in:'.$payload['calibration_fingerprint']],
            'theta' => ['required', 'numeric', 'between:-6,6'],
            'posterior_sd' => ['required', 'numeric', 'between:0,6'],
            'interval_95' => ['required', 'array', 'size:2'],
            'interval_95.*' => ['required', 'numeric', 'between:-6,6'],
            'evidence_count' => ['required', 'integer', 'in:'.count($payload['responses'])],
            'coverage_satisfied' => ['required', 'boolean'],
            'action' => ['required', 'in:select,stop'],
            'stop_reason' => ['present', 'nullable', 'in:max_length,precision,pool_exhausted'],
            'selected_item_id' => ['present', 'nullable', 'string'],
            'experimental_classification' => ['required', 'in:undetermined,above_cutpoint,below_cutpoint,uncertain'],
            'interpretation' => ['required', 'in:experimental_shadow_only'],
        ]);
        if ($validator->fails()) {
            throw new \RuntimeException('engine_invalid_response');
        }
        $data = $validator->validated();
        $eligible = collect($payload['items'])->where('eligible', true)->pluck('id');
        $used = collect($payload['responses'])->pluck('id');
        if ($data['interval_95'][0] > $data['interval_95'][1]
            || ($data['action'] === 'select' && ($data['stop_reason'] !== null || ! $eligible->containsStrict($data['selected_item_id']) || $used->containsStrict($data['selected_item_id'])))
            || ($data['action'] === 'stop' && ($data['selected_item_id'] !== null || $data['stop_reason'] === null))) {
            throw new \RuntimeException('engine_invalid_response');
        }

        $items = collect($payload['items'])->keyBy('id');
        $answered = collect($payload['responses'])->map(fn ($response) => $items[$response['id']]);
        $missingAreas = collect($payload['areas'])->filter(fn ($area) => $answered->where('area', $area)->count() < $payload['policy']['min_per_area']);
        $missingTopics = collect($payload['required_topics'])->diff($answered->pluck('topic'));
        $coverage = $missingAreas->isEmpty() && $missingTopics->isEmpty();
        $candidates = $items->where('eligible', true)->reject(fn ($item) => $used->containsStrict($item['id']));
        if ($missingAreas->isNotEmpty()) {
            $candidates = $candidates->where('area', $missingAreas->first());
        }
        if ($missingTopics->isNotEmpty()) {
            $relevant = $missingTopics->intersect($candidates->pluck('topic'));
            if ($relevant->isNotEmpty()) {
                $candidates = $candidates->where('topic', $relevant->first());
            } elseif ($missingAreas->isEmpty()) {
                $candidates = collect();
            }
        }
        $n = count($payload['responses']);
        $reason = $n >= $payload['policy']['max_questions'] ? 'max_length'
            : ($n >= $payload['policy']['min_questions'] && $coverage && $data['posterior_sd'] <= $payload['policy']['target_sd'] ? 'precision'
                : ($candidates->isEmpty() ? 'pool_exhausted' : null));
        $classification = 'undetermined';
        $cutpoint = $payload['policy']['cutpoint'];
        if ($reason === 'precision' && $cutpoint !== null) {
            $classification = $data['interval_95'][0] > $cutpoint ? 'above_cutpoint'
                : ($data['interval_95'][1] < $cutpoint ? 'below_cutpoint' : 'uncertain');
        }
        if ($data['coverage_satisfied'] !== $coverage || $data['stop_reason'] !== $reason
            || $data['experimental_classification'] !== $classification
            || ($data['action'] === 'select' && ! $candidates->pluck('id')->containsStrict($data['selected_item_id']))) {
            throw new \RuntimeException('engine_invalid_response');
        }

        return $data;
    }
}
