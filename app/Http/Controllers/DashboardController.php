<?php

namespace App\Http\Controllers;

use App\Services\CurrentContextService;
use App\Services\DashboardSummaryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardSummaryService $dashboard, CurrentContextService $contexts): Response
    {
        return Inertia::render(
            'Dashboard/Index',
            $dashboard->summary($request->user(), $contexts->current($request->user()))
        );
    }
}
