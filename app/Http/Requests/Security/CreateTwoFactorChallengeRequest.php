<?php

declare(strict_types=1);

namespace App\Http\Requests\Security;

use App\Enums\ElevatedAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateTwoFactorChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'totp_code' => [
                'required',
                'string',
                'regex:/^\d{6}$/',
            ],

            'action_payload' => [
                'required',
                'array',
            ],

            'action_payload.action' => [
                'required',
                'string',
                Rule::enum(ElevatedAction::class),
            ],

            'action_payload.source_wallet_id' => [
                'required',
                'uuid',
                'different:action_payload.destination_wallet_id',
            ],

            'action_payload.destination_wallet_id' => [
                'required',
                'uuid',
                'different:action_payload.source_wallet_id',
            ],

            'action_payload.source_amount_subunits' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'totp_code.regex' => 'The authentication code must contain exactly six digits.',
            'action_payload.source_wallet_id.different' => 'The source and destination wallets must be different.',
            'action_payload.destination_wallet_id.different' => 'The source and destination wallets must be different.',
        ];
    }
}
