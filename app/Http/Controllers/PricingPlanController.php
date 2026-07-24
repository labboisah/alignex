<?php

namespace App\Http\Controllers;

use App\Http\Resources\PricingPlanResource;
use App\Models\PricingPlan;
use App\Support\PlanFeatures;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PricingPlanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('PricingPlans/Index', [
            'plans' => PricingPlanResource::collection(
                PricingPlan::query()
                    ->withCount('registrations')
                    ->ordered()
                    ->get()
            ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        PricingPlan::create($this->validated($request));

        return redirect()
            ->route('pricing-plans.index')
            ->with('success', 'Pricing plan created.');
    }

    public function update(Request $request, PricingPlan $pricingPlan): RedirectResponse
    {
        $pricingPlan->update($this->validated($request, $pricingPlan));

        return redirect()
            ->route('pricing-plans.index')
            ->with('success', 'Pricing plan updated.');
    }

    public function destroy(PricingPlan $pricingPlan): RedirectResponse
    {
        if ($pricingPlan->registrations()->exists()) {
            return redirect()
                ->route('pricing-plans.index')
                ->with('error', 'This plan is linked to registrations and cannot be deleted. Deactivate it instead.');
        }

        $pricingPlan->delete();

        return redirect()
            ->route('pricing-plans.index')
            ->with('success', 'Pricing plan deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?PricingPlan $pricingPlan = null): array
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique(PricingPlan::class, 'slug')->ignore($pricingPlan)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:1000'],
            'price' => ['required', 'integer', 'min:0', 'max:999999999'],
            'currency' => ['required', 'string', 'max:10'],
            'billing_cycle' => ['required', Rule::in(['forever', 'monthly', 'yearly', 'contract'])],
            'delivery_modes' => ['array'],
            'delivery_modes.*' => ['string', Rule::in(['online', 'offline'])],
            'feature_flags' => ['array'],
            'feature_flags.*' => ['string', Rule::in(array_keys(PlanFeatures::labels()))],
            'highlights_text' => ['nullable', 'string', 'max:2000'],
            'max_candidates' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'max_exams_per_month' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'max_admin_users' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'max_devices' => ['required', 'integer', 'min:1', 'max:1000000'],
            'official_live_exam_allowed' => ['boolean'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'cta_label' => ['required', 'string', 'max:80'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);

        $validated['delivery_modes'] = array_values($validated['delivery_modes'] ?? []);
        $validated['highlights'] = collect(preg_split('/\r\n|\r|\n/', (string) ($validated['highlights_text'] ?? '')))
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();
        $validated['limits'] = [
            'max_candidates' => $validated['max_candidates'] ?? null,
            'max_exams_per_month' => $validated['max_exams_per_month'] ?? null,
            'max_admin_users' => $validated['max_admin_users'] ?? null,
            'max_devices' => $validated['max_devices'],
        ];
        $featureFlags = collect($validated['feature_flags'] ?? [])
            ->flip()
            ->map(fn (): bool => true)
            ->all();
        $validated['features'] = collect(PlanFeatures::labels())
            ->mapWithKeys(fn (string $label, string $key): array => [$key => (bool) ($featureFlags[$key] ?? false)])
            ->all();
        $validated['features']['online_delivery'] = in_array('online', $validated['delivery_modes'], true);
        $validated['features']['offline_delivery'] = in_array('offline', $validated['delivery_modes'], true);
        $validated['features']['official_live_exam_allowed'] = (bool) ($validated['official_live_exam_allowed'] ?? false);

        unset(
            $validated['feature_flags'],
            $validated['highlights_text'],
            $validated['max_candidates'],
            $validated['max_exams_per_month'],
            $validated['max_admin_users'],
            $validated['max_devices'],
            $validated['official_live_exam_allowed'],
        );

        return $validated;
    }
}
