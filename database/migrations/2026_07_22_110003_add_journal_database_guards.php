<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        match (DB::connection()->getDriverName()) {
            'sqlite' => $this->createSqliteTriggers(),
            'mysql' => $this->createMySqlTriggers(),
            default => null,
        };
    }

    public function down(): void
    {
        match (DB::connection()->getDriverName()) {
            'sqlite', 'mysql' => $this->dropTriggers(),
            default => null,
        };
    }

    private function createSqliteTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER journal_entries_status_insert_guard
            BEFORE INSERT ON journal_entries
            FOR EACH ROW WHEN NEW.status NOT IN ('draft', 'approved', 'posted', 'reversed', 'void')
            BEGIN
                SELECT RAISE(ABORT, 'Invalid journal status.');
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER journal_entries_status_update_guard
            BEFORE UPDATE ON journal_entries
            FOR EACH ROW WHEN NEW.status <> OLD.status
                AND NOT (
                    (OLD.status = 'draft' AND NEW.status IN ('approved', 'void'))
                    OR (OLD.status = 'approved' AND NEW.status = 'posted')
                    OR (OLD.status = 'posted' AND NEW.status = 'reversed')
                )
            BEGIN
                SELECT RAISE(ABORT, 'Illegal journal status transition.');
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER journal_entries_historical_update_guard
            BEFORE UPDATE ON journal_entries
            FOR EACH ROW WHEN OLD.status = 'reversed'
                OR (
                    OLD.status = 'posted'
                    AND (
                        NEW.status <> 'reversed'
                        OR NEW.source_type IS NOT OLD.source_type
                        OR NEW.source_id IS NOT OLD.source_id
                        OR NEW.journal_type IS NOT OLD.journal_type
                        OR NEW.source_version IS NOT OLD.source_version
                        OR NEW.journal_date IS NOT OLD.journal_date
                        OR NEW.journal_number IS NOT OLD.journal_number
                        OR NEW.posting_sequence IS NOT OLD.posting_sequence
                        OR NEW.gudang_id IS NOT OLD.gudang_id
                        OR NEW.contact_type IS NOT OLD.contact_type
                        OR NEW.contact_id IS NOT OLD.contact_id
                        OR NEW.description IS NOT OLD.description
                        OR NEW.total_debit IS NOT OLD.total_debit
                        OR NEW.total_credit IS NOT OLD.total_credit
                    )
                )
            BEGIN
                SELECT RAISE(ABORT, 'Posted or reversed journals are immutable.');
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER journal_entries_historical_delete_guard
            BEFORE DELETE ON journal_entries
            FOR EACH ROW WHEN OLD.status IN ('posted', 'reversed')
            BEGIN
                SELECT RAISE(ABORT, 'Posted or reversed journals cannot be deleted.');
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER journal_lines_amount_insert_guard
            BEFORE INSERT ON journal_lines
            FOR EACH ROW WHEN (NEW.debit > 0 AND NEW.credit > 0)
                OR (NEW.debit <= 0 AND NEW.credit <= 0)
            BEGIN
                SELECT RAISE(ABORT, 'Journal lines require exactly one positive amount.');
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER journal_lines_amount_update_guard
            BEFORE UPDATE ON journal_lines
            FOR EACH ROW WHEN (NEW.debit > 0 AND NEW.credit > 0)
                OR (NEW.debit <= 0 AND NEW.credit <= 0)
            BEGIN
                SELECT RAISE(ABORT, 'Journal lines require exactly one positive amount.');
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER journal_lines_historical_insert_guard
            BEFORE INSERT ON journal_lines
            FOR EACH ROW WHEN (SELECT status FROM journal_entries WHERE id = NEW.journal_entry_id) IN ('posted', 'reversed')
            BEGIN
                SELECT RAISE(ABORT, 'Historical journal lines are immutable.');
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER journal_lines_historical_update_guard
            BEFORE UPDATE ON journal_lines
            FOR EACH ROW WHEN (SELECT status FROM journal_entries WHERE id = OLD.journal_entry_id) IN ('posted', 'reversed')
            BEGIN
                SELECT RAISE(ABORT, 'Historical journal lines are immutable.');
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER journal_lines_historical_delete_guard
            BEFORE DELETE ON journal_lines
            FOR EACH ROW WHEN (SELECT status FROM journal_entries WHERE id = OLD.journal_entry_id) IN ('posted', 'reversed')
            BEGIN
                SELECT RAISE(ABORT, 'Historical journal lines are immutable.');
            END
        SQL);
    }

    private function createMySqlTriggers(): void
    {
        DB::unprepared("CREATE TRIGGER journal_entries_status_insert_guard BEFORE INSERT ON journal_entries FOR EACH ROW BEGIN IF NEW.status NOT IN ('draft', 'approved', 'posted', 'reversed', 'void') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid journal status.'; END IF; END");
        DB::unprepared("CREATE TRIGGER journal_entries_status_update_guard BEFORE UPDATE ON journal_entries FOR EACH ROW BEGIN IF NEW.status <> OLD.status AND NOT ((OLD.status = 'draft' AND NEW.status IN ('approved', 'void')) OR (OLD.status = 'approved' AND NEW.status = 'posted') OR (OLD.status = 'posted' AND NEW.status = 'reversed')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Illegal journal status transition.'; END IF; END");
        DB::unprepared("CREATE TRIGGER journal_entries_historical_update_guard BEFORE UPDATE ON journal_entries FOR EACH ROW BEGIN IF OLD.status = 'reversed' OR (OLD.status = 'posted' AND (NEW.status <> 'reversed' OR NOT (NEW.source_type <=> OLD.source_type) OR NOT (NEW.source_id <=> OLD.source_id) OR NOT (NEW.journal_type <=> OLD.journal_type) OR NOT (NEW.source_version <=> OLD.source_version) OR NOT (NEW.journal_date <=> OLD.journal_date) OR NOT (NEW.journal_number <=> OLD.journal_number) OR NOT (NEW.posting_sequence <=> OLD.posting_sequence) OR NOT (NEW.gudang_id <=> OLD.gudang_id) OR NOT (NEW.contact_type <=> OLD.contact_type) OR NOT (NEW.contact_id <=> OLD.contact_id) OR NOT (NEW.description <=> OLD.description) OR NOT (NEW.total_debit <=> OLD.total_debit) OR NOT (NEW.total_credit <=> OLD.total_credit))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted or reversed journals are immutable.'; END IF; END");
        DB::unprepared("CREATE TRIGGER journal_entries_historical_delete_guard BEFORE DELETE ON journal_entries FOR EACH ROW BEGIN IF OLD.status IN ('posted', 'reversed') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted or reversed journals cannot be deleted.'; END IF; END");
        DB::unprepared("CREATE TRIGGER journal_lines_amount_insert_guard BEFORE INSERT ON journal_lines FOR EACH ROW BEGIN IF (NEW.debit > 0 AND NEW.credit > 0) OR (NEW.debit <= 0 AND NEW.credit <= 0) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Journal lines require exactly one positive amount.'; END IF; IF (SELECT status FROM journal_entries WHERE id = NEW.journal_entry_id) IN ('posted', 'reversed') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historical journal lines are immutable.'; END IF; END");
        DB::unprepared("CREATE TRIGGER journal_lines_amount_update_guard BEFORE UPDATE ON journal_lines FOR EACH ROW BEGIN IF (NEW.debit > 0 AND NEW.credit > 0) OR (NEW.debit <= 0 AND NEW.credit <= 0) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Journal lines require exactly one positive amount.'; END IF; IF (SELECT status FROM journal_entries WHERE id = OLD.journal_entry_id) IN ('posted', 'reversed') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historical journal lines are immutable.'; END IF; END");
        DB::unprepared("CREATE TRIGGER journal_lines_historical_delete_guard BEFORE DELETE ON journal_lines FOR EACH ROW BEGIN IF (SELECT status FROM journal_entries WHERE id = OLD.journal_entry_id) IN ('posted', 'reversed') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historical journal lines are immutable.'; END IF; END");
    }

    private function dropTriggers(): void
    {
        foreach ([
            'journal_lines_historical_delete_guard',
            'journal_lines_historical_update_guard',
            'journal_lines_historical_insert_guard',
            'journal_lines_amount_update_guard',
            'journal_lines_amount_insert_guard',
            'journal_entries_historical_delete_guard',
            'journal_entries_historical_update_guard',
            'journal_entries_status_update_guard',
            'journal_entries_status_insert_guard',
        ] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }
};
