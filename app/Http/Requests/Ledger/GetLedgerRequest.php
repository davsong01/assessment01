<?php

declare(strict_types=1);

namespace App\Http\Requests\Ledger;

use App\Http\Requests\ApiRequest;

final class GetLedgerRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 20);
    }
}
