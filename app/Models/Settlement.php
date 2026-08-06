<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SettlementStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $provider_reference
 * @property string|null $ledger_transaction_id
 * @property SettlementStatus $status
 * @property array<string, mixed> $payload
 */
final class Settlement extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider_reference',
        'ledger_transaction_id',
        'status',
        'payload',
        'completed_at',
        'failed_at',
    ];

    /**
     * @return BelongsTo<LedgerTransaction, $this>
     */
    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(
            LedgerTransaction::class
        );
    }

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'status' => SettlementStatus::class,
            'payload' => 'array',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }
}
