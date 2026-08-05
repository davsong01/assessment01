<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use Database\Factories\LedgerTransactionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerTransaction extends Model
{
    /** @use HasFactory<LedgerTransactionFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'type',
        'status',
        'metadata',
        'posted_at',
    ];

    /**
     * @return HasMany<LedgerEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /**
     * @return array<string, class-string|string>
     */
    protected function casts(): array
    {
        return [
            'type' => LedgerTransactionType::class,
            'status' => LedgerTransactionStatus::class,
            'metadata' => 'array',
            'posted_at' => 'immutable_datetime',
        ];
    }
}
