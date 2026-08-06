<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\WalletStatus;
use App\Enums\WalletType;
use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property int|null $user_id
 * @property Currency $currency
 * @property WalletType $type
 * @property WalletStatus $status
 */
class Wallet extends Model
{
    /** @use HasFactory<WalletFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'currency',
        'type',
        'status',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<LedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /**
     * @return array<string, class-string|string>
     */
    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'type' => WalletType::class,
            'status' => WalletStatus::class,
        ];
    }
}
