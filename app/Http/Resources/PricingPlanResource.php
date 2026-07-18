<?php

namespace App\Http\Resources;

use App\Support\PlanFeatures;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PricingPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'formatted_price' => $this->formattedPrice(),
            'currency' => $this->currency,
            'billing_cycle' => $this->billing_cycle,
            'billing_label' => $this->billingLabel(),
            'delivery_modes' => $this->delivery_modes ?? [],
            'limits' => $this->limits ?? [],
            'features' => $this->features ?? [],
            'feature_items' => $this->featureItems(),
            'highlights' => $this->highlights ?? [],
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'cta_label' => $this->cta_label,
            'sort_order' => $this->sort_order,
            'registrations_count' => $this->whenCounted('registrations'),
        ];
    }

    private function formattedPrice(): string
    {
        if ((int) $this->price === 0) {
            return $this->currency.' 0';
        }

        if ($this->billing_cycle === 'contract') {
            return 'From '.$this->currency.' '.number_format((int) $this->price);
        }

        return $this->currency.' '.number_format((int) $this->price);
    }

    private function billingLabel(): string
    {
        return match ($this->billing_cycle) {
            'forever' => 'forever',
            'contract' => 'per year or contract',
            'monthly' => 'per month',
            default => 'per year',
        };
    }

    /**
     * @return array<int, array{key: string, label: string, enabled: bool}>
     */
    private function featureItems(): array
    {
        $features = $this->features ?? [];

        return collect(self::featureLabels())
            ->map(fn (string $label, string $key): array => [
                'key' => $key,
                'label' => $label,
                'enabled' => (bool) ($features[$key] ?? false),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function featureLabels(): array
    {
        return PlanFeatures::labels();
    }
}
