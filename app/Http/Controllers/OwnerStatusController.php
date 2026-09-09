<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOwnerStatusRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class OwnerStatusController extends Controller
{
    public function __invoke(UpdateOwnerStatusRequest $request): RedirectResponse
    {
        $owner = $request->owner();
        $owner->getConnection()->transaction(function () use ($owner, $request): void {
            $locked = $owner->newQuery()->whereKey($owner->getKey())->lockForUpdate()->firstOrFail();
            $locked->update($request->validated());
            Log::info('Owner status changed', ['type' => $owner->getTable(), 'id' => $owner->getKey(), 'actor_id' => $request->user()->id, 'status' => $request->validated('status')]);
        });

        return back()->with('success', 'Status updated. Existing records have been retained.');
    }
}
