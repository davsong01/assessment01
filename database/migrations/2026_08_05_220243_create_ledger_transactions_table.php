<?php

declare(strict_types=1);

use App\Enums\LedgerTransactionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'ledger_transactions',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('reference', 100)->unique();
                $table->string('type', 40);
                $table->string('status', 20)
                    ->default(LedgerTransactionStatus::Pending->value);

                $table->json('metadata')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->timestamps();

                $table->index(
                    ['status', 'created_at'],
                    'ledger_transactions_status_created_index'
                );

                $table->index(
                    ['type', 'status'],
                    'ledger_transactions_type_status_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_transactions');
    }
};
