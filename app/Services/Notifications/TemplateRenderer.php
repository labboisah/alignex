<?php

namespace App\Services\Notifications;

use Illuminate\Support\Arr;

class TemplateRenderer
{
    public function render(?string $template, array $context): string
    {
        if ($template === null) {
            return '';
        }

        return preg_replace_callback('/{{\s*([A-Za-z0-9_.-]+)\s*}}/', function (array $matches) use ($context): string {
            $value = Arr::get($context, $matches[1], '');

            if (is_array($value) || is_object($value)) {
                return '';
            }

            return (string) $value;
        }, $template) ?? '';
    }
}
