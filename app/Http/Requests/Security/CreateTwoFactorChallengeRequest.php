<?php

declare(strict_types=1);

namespace App\Http\Requests\Security;

use App\Data\Security\ElevatedActionPayload;
use App\Enums\ElevatedAction;
use App\Http\Requests\ApiRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

final class CreateTwoFactorChallengeRequest extends ApiRequest
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
                'array:action,source_wallet_id,destination_wallet_id,source_amount_subunits',
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

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'status' => false,
                'message' => 'The given data was invalid.',
                'error' => [
                    'type' => 'VALIDATION_ERROR',
                    'details' => $validator->errors(),
                ],
            ], 422)
        );
    }

    public function actionPayload(): ElevatedActionPayload
    {
        /** @var array{
         *     action: string,
         *     source_wallet_id: string,
         *     destination_wallet_id: string,
         *     source_amount_subunits: int
         * } $payload
         */
        $payload = $this->validated('action_payload');

        return ElevatedActionPayload::fromArray($payload);
    }

    public function totpCode(): string
    {
        return $this->string('totp_code')->toString();
    }
}
