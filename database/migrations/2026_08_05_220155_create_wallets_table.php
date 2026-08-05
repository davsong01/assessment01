<?php

declare(strict_types=1);

use App\Enums\Currency;
use App\Enums\WalletStatus;
use App\Enums\WalletType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();

            $table->char('currency', 3);
            $table->string('type', 30);
            $table->string('status', 20)
                ->default(WalletStatus::Active->value);

            $table->timestamps();

            $table->unique(
                ['user_id', 'currency', 'type'],
                'wallets_user_currency_type_unique'
            );

            $table->index(
                ['user_id', 'status'],
                'wallets_user_status_index'
            );

            $table->index(
                ['currency', 'type', 'status'],
                'wallets_currency_type_status_index'
            );
        });

        $currencies = implode(
            "', '",
            array_column(Currency::cases(), 'value')
        );

        $types = implode(
            "', '",
            array_column(WalletType::cases(), 'value')
        );

        $statuses = implode(
            "', '",
            array_column(WalletStatus::cases(), 'value')
        );

        DB::statement(
            "ALTER TABLE wallets
             ADD CONSTRAINT wallets_currency_check
             CHECK (currency IN ('{$currencies}'))"
        );

        DB::statement(
            "ALTER TABLE wallets
             ADD CONSTRAINT wallets_type_check
             CHECK (type IN ('{$types}'))"
        );

        DB::statement(
            "ALTER TABLE wallets
             ADD CONSTRAINT wallets_status_check
             CHECK (status IN ('{$statuses}'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
