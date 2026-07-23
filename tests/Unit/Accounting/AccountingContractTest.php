<?php

declare(strict_types=1);

namespace Tests\Unit\Accounting;

use App\Accounting\AccountCategory;
use App\Accounting\CashAccountType;
use App\Accounting\DomainException;
use App\Accounting\IllegalTransitionException;
use App\Accounting\JournalSource;
use App\Accounting\JournalStatus;
use App\Accounting\MappingKey;
use App\Accounting\NormalBalance;
use App\Accounting\StatementClassification;
use App\Accounting\TransferStatus;
use App\Accounting\UnknownMappingKeyException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AccountingContractTest extends TestCase
{
    // ── AccountCategory defaults ──────────────────────────────────────

    #[Test]
    public function aset_defaults_to_debit_and_neraca(): void
    {
        $cat = AccountCategory::Aset;
        $this->assertSame(NormalBalance::Debit, $cat->normalBalance());
        $this->assertSame(StatementClassification::Neraca, $cat->statementClassification());
    }

    #[Test]
    public function kewajiban_defaults_to_kredit_and_neraca(): void
    {
        $cat = AccountCategory::Kewajiban;
        $this->assertSame(NormalBalance::Kredit, $cat->normalBalance());
        $this->assertSame(StatementClassification::Neraca, $cat->statementClassification());
    }

    #[Test]
    public function ekuitas_defaults_to_kredit_and_neraca(): void
    {
        $cat = AccountCategory::Ekuitas;
        $this->assertSame(NormalBalance::Kredit, $cat->normalBalance());
        $this->assertSame(StatementClassification::Neraca, $cat->statementClassification());
    }

    #[Test]
    public function pendapatan_defaults_to_kredit_and_laba_rugi(): void
    {
        $cat = AccountCategory::Pendapatan;
        $this->assertSame(NormalBalance::Kredit, $cat->normalBalance());
        $this->assertSame(StatementClassification::LabaRugi, $cat->statementClassification());
    }

    #[Test]
    public function hpp_defaults_to_debit_and_laba_rugi(): void
    {
        $cat = AccountCategory::Hpp;
        $this->assertSame(NormalBalance::Debit, $cat->normalBalance());
        $this->assertSame(StatementClassification::LabaRugi, $cat->statementClassification());
    }

    #[Test]
    public function beban_defaults_to_debit_and_laba_rugi(): void
    {
        $cat = AccountCategory::Beban;
        $this->assertSame(NormalBalance::Debit, $cat->normalBalance());
        $this->assertSame(StatementClassification::LabaRugi, $cat->statementClassification());
    }

    #[Test]
    public function pendapatan_lainnya_defaults_to_kredit_and_laba_rugi(): void
    {
        $cat = AccountCategory::PendapatanLainnya;
        $this->assertSame(NormalBalance::Kredit, $cat->normalBalance());
        $this->assertSame(StatementClassification::LabaRugi, $cat->statementClassification());
    }

    #[Test]
    public function beban_lainnya_defaults_to_debit_and_laba_rugi(): void
    {
        $cat = AccountCategory::BebanLainnya;
        $this->assertSame(NormalBalance::Debit, $cat->normalBalance());
        $this->assertSame(StatementClassification::LabaRugi, $cat->statementClassification());
    }

    // ── NormalBalance / StatementClassification enums ─────────────────

    #[Test]
    public function normal_balance_has_exactly_two_cases(): void
    {
        $this->assertCount(2, NormalBalance::cases());
        $this->assertSame('debit', NormalBalance::Debit->value);
        $this->assertSame('kredit', NormalBalance::Kredit->value);
    }

    #[Test]
    public function statement_classification_has_exactly_two_cases(): void
    {
        $this->assertCount(2, StatementClassification::cases());
        $this->assertSame('neraca', StatementClassification::Neraca->value);
        $this->assertSame('laba_rugi', StatementClassification::LabaRugi->value);
    }

    // ── MappingKey ────────────────────────────────────────────────────

    #[Test]
    public function mapping_key_ar_receivable_accepts_only_asset_categories(): void
    {
        $key = MappingKey::from('ar.receivable');
        $compatible = $key->compatibleCategories();

        $this->assertContains(AccountCategory::Aset, $compatible);
        $this->assertNotContains(AccountCategory::Kewajiban, $compatible);
        $this->assertNotContains(AccountCategory::Pendapatan, $compatible);
        $this->assertNotContains(AccountCategory::Beban, $compatible);
    }

    #[Test]
    public function mapping_key_ap_payable_accepts_only_liability_categories(): void
    {
        $key = MappingKey::from('ap.payable');
        $compatible = $key->compatibleCategories();

        $this->assertContains(AccountCategory::Kewajiban, $compatible);
        $this->assertNotContains(AccountCategory::Aset, $compatible);
    }

    #[Test]
    public function mapping_key_sales_retail_revenue_accepts_revenue_categories(): void
    {
        $key = MappingKey::from('sales.retail_revenue');
        $compatible = $key->compatibleCategories();

        $this->assertContains(AccountCategory::Pendapatan, $compatible);
        $this->assertNotContains(AccountCategory::Beban, $compatible);
    }

    #[Test]
    public function unknown_mapping_key_throws_typed_exception(): void
    {
        $this->expectException(UnknownMappingKeyException::class);
        MappingKey::fromString('nonexistent.key');
    }

    // ── JournalSource ─────────────────────────────────────────────────

    #[Test]
    public function journal_source_has_expected_cases(): void
    {
        $values = array_map(fn (JournalSource $s) => $s->value, JournalSource::cases());

        $this->assertContains('sale', $values);
        $this->assertContains('purchase', $values);
        $this->assertContains('payment', $values);
        $this->assertContains('transfer', $values);
        $this->assertContains('manual', $values);
        $this->assertContains('opening_balance', $values);
    }

    // ── JournalStatus transitions ─────────────────────────────────────

    #[Test]
    public function journal_status_draft_can_transition_to_approved(): void
    {
        $this->assertTrue(JournalStatus::Draft->canTransitionTo(JournalStatus::Approved));
    }

    #[Test]
    public function journal_status_approved_can_transition_to_posted(): void
    {
        $this->assertTrue(JournalStatus::Approved->canTransitionTo(JournalStatus::Posted));
    }

    #[Test]
    public function journal_status_posted_can_transition_to_reversed(): void
    {
        $this->assertTrue(JournalStatus::Posted->canTransitionTo(JournalStatus::Reversed));
    }

    #[Test]
    public function journal_status_draft_can_transition_to_void(): void
    {
        $this->assertTrue(JournalStatus::Draft->canTransitionTo(JournalStatus::Void));
    }

    #[Test]
    public function journal_status_posted_cannot_transition_to_draft(): void
    {
        $this->assertFalse(JournalStatus::Posted->canTransitionTo(JournalStatus::Draft));
    }

    #[Test]
    public function illegal_journal_transition_throws_typed_exception(): void
    {
        $this->expectException(IllegalTransitionException::class);
        JournalStatus::Posted->transitionTo(JournalStatus::Draft);
    }

    #[Test]
    public function legal_journal_transition_does_not_throw(): void
    {
        $next = JournalStatus::Draft->transitionTo(JournalStatus::Approved);
        $this->assertSame(JournalStatus::Approved, $next);
    }

    // ── CashAccountType ───────────────────────────────────────────────

    #[Test]
    public function cash_account_type_has_kas_and_bank(): void
    {
        $values = array_map(fn (CashAccountType $t) => $t->value, CashAccountType::cases());
        $this->assertContains('kas', $values);
        $this->assertContains('bank', $values);
        $this->assertCount(2, CashAccountType::cases());
    }

    // ── TransferStatus ────────────────────────────────────────────────

    #[Test]
    public function transfer_status_has_direct_and_in_transit(): void
    {
        $values = array_map(fn (TransferStatus $s) => $s->value, TransferStatus::cases());
        $this->assertContains('direct', $values);
        $this->assertContains('in_transit', $values);
    }

    // ── DomainException hierarchy ─────────────────────────────────────

    #[Test]
    public function unknown_mapping_key_exception_extends_domain_exception(): void
    {
        $e = new UnknownMappingKeyException('foo.bar');
        $this->assertInstanceOf(DomainException::class, $e);
        $this->assertStringContainsString('foo.bar', $e->getMessage());
    }

    #[Test]
    public function illegal_transition_exception_extends_domain_exception(): void
    {
        $e = new IllegalTransitionException('posted', 'draft');
        $this->assertInstanceOf(DomainException::class, $e);
        $this->assertStringContainsString('posted', $e->getMessage());
        $this->assertStringContainsString('draft', $e->getMessage());
    }

    // ── No floats anywhere ────────────────────────────────────────────

    #[Test]
    public function account_category_values_are_string_backed(): void
    {
        foreach (AccountCategory::cases() as $case) {
            $this->assertIsString($case->value);
        }
    }
}
