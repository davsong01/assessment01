<?php

declare(strict_types=1);

use App\Enums\SettlementStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'settlements',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->string(
                    'provider_reference',
                    120
                )->unique();

                $table->uuid(
                    'ledger_transaction_id'
                )->nullable();

                $table->string('status', 30)
                    ->default(
                        SettlementStatus::Initiated->value
                    );

                $table->json('payload');

                $table->timestamp(
                    'completed_at'
                )->nullable();

                $table->timestamp(
                    'failed_at'
                )->nullable();

                $table->timestamps();

                $table->foreign('ledger_transaction_id')
                    ->references('id')
                    ->on('ledger_transactions')
                    ->nullOnDelete();

                $table->index(
                    ['status', 'created_at'],
                    'settlements_status_created_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
