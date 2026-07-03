<?php

namespace App\Http\Controllers;

use App\Services\CurrentContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CurrentContextController extends Controller
{
    public function update(Request $request, CurrentContextService $contexts): RedirectResponse
    {
        $data = $request->validate([
            'context_type' => ['required', Rule::in(CurrentContextService::TYPES)],
            'context_id' => ['required', 'integer'],
        ]);

        $context = $contexts->switch($request->user(), $data['context_type'], $data['context_id']);

        return back()->with('success', 'Switched to '.$context['name'].'.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $request->user()->forceFill([
            'active_context_type' => null,
            'active_context_id' => null,
        ])->save();

        return back()->with('success', 'Switched to platform-wide dashboard.');
    }
}
