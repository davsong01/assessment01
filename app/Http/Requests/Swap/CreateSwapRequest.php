<?php

declare(strict_types=1);

namespace App\Http\Requests\Swap;

use App\Data\Security\ElevatedActionPayload;
use App\Enums\ElevatedAction;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

final class CreateSwapRequest extends ApiRequest
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
            'action' => [
                'required',
                'string',
                Rule::enum(ElevatedAction::class),
                Rule::in([ElevatedAction::Swap->value]),
            ],

            'source_wallet_id' => [
                'required',
                'uuid',
                'different:destination_wallet_id',
            ],

            'destination_wallet_id' => [
                'required',
                'uuid',
                'different:source_wallet_id',
            ],

            'source_amount_subunits' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }

    public function actionPayload(): ElevatedActionPayload
    {
        /** @var array{
         *     action: string,
         *     source_wallet_id: string,
         *     destination_wallet_id: string,
         *     source_amount_subunits: int
         * } $validated
         */
        $validated = $this->validated();

        return ElevatedActionPayload::fromArray($validated);
    }

    public function elevatedActionToken(): ?string
    {
        $token = $this->header(
            'X-Elevated-Action-Token'
        );

        return is_string($token) && $token !== ''
            ? $token
            : null;
    }
}
