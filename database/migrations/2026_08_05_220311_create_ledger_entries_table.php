<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->id();

            $table->foreignUuid('ledger_transaction_id')
                ->constrained('ledger_transactions')
                ->restrictOnDelete();

            $table->foreignUuid('wallet_id')
                ->constrained('wallets')
                ->restrictOnDelete();

            $table->char('currency', 3);
            $table->string('entry_type', 10);
            $table->bigInteger('amount_subunits');

            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['wallet_id', 'id'],
                'ledger_entries_wallet_id_index'
            );

            $table->index(
                ['wallet_id', 'created_at'],
                'ledger_entries_wallet_created_index'
            );

            $table->index(
                ['ledger_transaction_id', 'wallet_id'],
                'ledger_entries_transaction_wallet_index'
            );
        });

        DB::statement(
            'ALTER TABLE ledger_entries
             ADD CONSTRAINT ledger_entries_non_zero_amount_check
             CHECK (amount_subunits <> 0)'
        );

        DB::statement(
            "ALTER TABLE ledger_entries
             ADD CONSTRAINT ledger_entries_sign_check
             CHECK (
                 (entry_type = 'credit' AND amount_subunits > 0)
                 OR
                 (entry_type = 'debit' AND amount_subunits < 0)
             )"
        );

        DB::statement(
            "ALTER TABLE ledger_entries
             ADD CONSTRAINT ledger_entries_currency_check
             CHECK (currency IN ('NGN', 'CNY'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
