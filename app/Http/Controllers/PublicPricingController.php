<?php

namespace App\Http\Controllers;

use App\Http\Resources\PricingPlanResource;
use App\Models\PricingPlan;
use Inertia\Inertia;
use Inertia\Response;

class PublicPricingController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Public/Pricing', [
            'plans' => PricingPlanResource::collection(
                PricingPlan::query()
                    ->where('is_active', true)
                    ->ordered()
                    ->get()
            ),
        ]);
    }
}
