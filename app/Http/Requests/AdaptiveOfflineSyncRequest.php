<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdaptiveOfflineSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['state_hash' => ['required', 'regex:/^[a-f0-9]{64}$/'], 'commands' => ['required', 'array', 'min:1', 'max:50000'],
            'commands.*' => ['array:key,op,at,item_id,option_ids,state_version,event_name'],
            'commands.*.key' => ['required', 'string', 'min:8', 'max:80'],
            'commands.*.op' => ['required', 'in:start,next-level,draft,commit,submit,tick,supervisor_end,disqualified,event'],
            'commands.*.event_name' => ['sometimes', 'in:tab_blur,focus,fullscreen_exit,copy_attempt,paste_attempt,network_reconnect'],
            'commands.*.at' => ['required', 'integer', 'min:1'], 'commands.*.item_id' => ['nullable', 'string', 'max:80'],
            'commands.*.option_ids' => ['sometimes', 'array', 'max:100'], 'commands.*.option_ids.*' => ['string', 'max:80'],
            'commands.*.state_version' => ['sometimes', 'integer', 'min:0']];
    }
}
