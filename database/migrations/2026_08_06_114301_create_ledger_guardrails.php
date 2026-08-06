<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->createEntryInsertTrigger();
        $this->createEntryUpdateTrigger();
        $this->createEntryDeleteTrigger();
        $this->createTransactionStatusTrigger();
    }

    public function down(): void
    {
        DB::unprepared(
            'DROP TRIGGER IF EXISTS prevent_posted_transaction_status_change'
        );

        DB::unprepared(
            'DROP TRIGGER IF EXISTS prevent_posted_ledger_entry_delete'
        );

        DB::unprepared(
            'DROP TRIGGER IF EXISTS prevent_posted_ledger_entry_update'
        );

        DB::unprepared(
            'DROP TRIGGER IF EXISTS prevent_posted_ledger_entry_insert'
        );

    }

    private function createEntryInsertTrigger(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(
                <<<'SQL'
CREATE TRIGGER prevent_posted_ledger_entry_insert
BEFORE INSERT ON ledger_entries
FOR EACH ROW
WHEN (
    SELECT status
    FROM ledger_transactions
    WHERE id = NEW.ledger_transaction_id
) = 'posted'
BEGIN
    SELECT RAISE(ABORT, 'Entries cannot be added to a posted ledger transaction.');
END
SQL
            );

            return;
        }

        DB::unprepared(
            <<<'SQL'
CREATE TRIGGER prevent_posted_ledger_entry_insert
BEFORE INSERT ON ledger_entries
FOR EACH ROW
BEGIN
    DECLARE transaction_status VARCHAR(20);

    SELECT status
    INTO transaction_status
    FROM ledger_transactions
    WHERE id = NEW.ledger_transaction_id;

    IF transaction_status = 'posted' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'Entries cannot be added to a posted ledger transaction.';
    END IF;
END
SQL
        );
    }

    private function createEntryUpdateTrigger(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(
                <<<'SQL'
CREATE TRIGGER prevent_posted_ledger_entry_update
BEFORE UPDATE ON ledger_entries
FOR EACH ROW
WHEN (
    SELECT status
    FROM ledger_transactions
    WHERE id = OLD.ledger_transaction_id
) = 'posted'
BEGIN
    SELECT RAISE(ABORT, 'Posted ledger entries are immutable.');
END
SQL
            );

            return;
        }

        DB::unprepared(
            <<<'SQL'
CREATE TRIGGER prevent_posted_ledger_entry_update
BEFORE UPDATE ON ledger_entries
FOR EACH ROW
BEGIN
    DECLARE transaction_status VARCHAR(20);

    SELECT status
    INTO transaction_status
    FROM ledger_transactions
    WHERE id = OLD.ledger_transaction_id;

    IF transaction_status = 'posted' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'Posted ledger entries are immutable.';
    END IF;
END
SQL
        );
    }

    private function createEntryDeleteTrigger(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(
                <<<'SQL'
CREATE TRIGGER prevent_posted_ledger_entry_delete
BEFORE DELETE ON ledger_entries
FOR EACH ROW
WHEN (
    SELECT status
    FROM ledger_transactions
    WHERE id = OLD.ledger_transaction_id
) = 'posted'
BEGIN
    SELECT RAISE(ABORT, 'Posted ledger entries cannot be deleted.');
END
SQL
            );

            return;
        }

        DB::unprepared(
            <<<'SQL'
CREATE TRIGGER prevent_posted_ledger_entry_delete
BEFORE DELETE ON ledger_entries
FOR EACH ROW
BEGIN
    DECLARE transaction_status VARCHAR(20);

    SELECT status
    INTO transaction_status
    FROM ledger_transactions
    WHERE id = OLD.ledger_transaction_id;

    IF transaction_status = 'posted' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'Posted ledger entries cannot be deleted.';
    END IF;
END
SQL
        );
    }

    private function createTransactionStatusTrigger(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(
                <<<'SQL'
CREATE TRIGGER prevent_posted_transaction_status_change
BEFORE UPDATE ON ledger_transactions
FOR EACH ROW
WHEN OLD.status = 'posted' AND NEW.status <> 'posted'
BEGIN
    SELECT RAISE(ABORT, 'A posted ledger transaction cannot change status.');
END
SQL
            );

            return;
        }

        DB::unprepared(
            <<<'SQL'
CREATE TRIGGER prevent_posted_transaction_status_change
BEFORE UPDATE ON ledger_transactions
FOR EACH ROW
BEGIN
    IF OLD.status = 'posted'
        AND NEW.status <> 'posted'
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'A posted ledger transaction cannot change status.';
    END IF;
END
SQL
        );
    }
};
