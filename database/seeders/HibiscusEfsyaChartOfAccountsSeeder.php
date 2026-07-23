<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Accounting\AccountCategory;
use App\Accounting\NormalBalance;
use App\Accounting\StatementClassification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic Hibiscus Efsya Chart of Accounts seeder.
 *
 * Seeds a single-company COA based on the accounting-foundation-roadmap.md
 * structure (lines 446-571). Uses upsert for idempotency.
 *
 * Does NOT create warehouse-specific cash accounts (those are configured
 * later via Todo 7 cash/bank masters).
 */
class HibiscusEfsyaChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = $this->buildChart();

        // Two-pass approach:
        // 1. Insert new accounts or update existing catalog metadata
        // 2. Repair parent_id references (hierarchy)
        //
        // Runtime state (is_used, created_at, updated_at) is preserved on existing accounts.
        // Only catalog metadata is reconciled to match the deterministic chart definition.

        $inserted = [];

        foreach ($accounts as $index => $account) {
            $code = $account['code'];

            // Check if account already exists
            $existingId = DB::table('accounts')->where('code', $code)->value('id');

            if ($existingId === null) {
                // New account: insert with all fields
                DB::table('accounts')->insert([
                    'code' => $code,
                    'name' => $account['name'],
                    'parent_id' => null, // Will be set in second pass
                    'category' => $account['category']->value,
                    'subcategory' => $account['subcategory'] ?? null,
                    'normal_balance' => $account['normal_balance']->value,
                    'statement_classification' => $account['statement_classification']->value,
                    'cash_flow_category' => $account['cash_flow_category'] ?? null,
                    'cash_flow_line' => $account['cash_flow_line'] ?? null,
                    'is_postable' => $account['is_postable'] ?? true,
                    'is_control_account' => $account['is_control_account'] ?? false,
                    'is_system' => $account['is_system'] ?? false,
                    'is_active' => $account['is_active'] ?? true,
                    'is_used' => false,
                    'display_order' => $account['display_order'] ?? $index,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $inserted[$code] = DB::table('accounts')->where('code', $code)->value('id');
            } else {
                // Existing account: update only catalog metadata
                // Do NOT touch: is_used, created_at, updated_at
                DB::table('accounts')->where('id', $existingId)->update([
                    'name' => $account['name'],
                    'category' => $account['category']->value,
                    'subcategory' => $account['subcategory'] ?? null,
                    'normal_balance' => $account['normal_balance']->value,
                    'statement_classification' => $account['statement_classification']->value,
                    'cash_flow_category' => $account['cash_flow_category'] ?? null,
                    'cash_flow_line' => $account['cash_flow_line'] ?? null,
                    'is_postable' => $account['is_postable'] ?? true,
                    'is_control_account' => $account['is_control_account'] ?? false,
                    'is_system' => $account['is_system'] ?? false,
                    'is_active' => $account['is_active'] ?? true,
                    'display_order' => $account['display_order'] ?? $index,
                ]);

                $inserted[$code] = $existingId;
            }
        }

        // Second pass: repair parent_id references (hierarchy)
        foreach ($accounts as $account) {
            if (isset($account['parent_code'])) {
                $parentId = $inserted[$account['parent_code']] ?? null;

                if ($parentId !== null) {
                    DB::table('accounts')
                        ->where('code', $account['code'])
                        ->update(['parent_id' => $parentId]);
                }
            }
        }
    }

    /**
     * Build the complete Hibiscus Efsya chart of accounts.
     *
     * @return list<array<string, mixed>>
     */
    private function buildChart(): array
    {
        $accounts = [];

        // ─────────────────────────────────────────────────────────────────
        // 1-0000 ASET
        // ─────────────────────────────────────────────────────────────────
        $accounts[] = [
            'code' => '1-0000',
            'name' => 'ASET',
            'category' => AccountCategory::Aset,
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => false,
            'is_system' => true,
            'display_order' => 100,
        ];

        // 1-1000 Aset Lancar
        $accounts[] = [
            'code' => '1-1000',
            'name' => 'Aset Lancar',
            'parent_code' => '1-0000',
            'category' => AccountCategory::Aset,
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => false,
            'is_system' => true,
            'display_order' => 110,
        ];

        // 1-1100 Kas
        $accounts[] = [
            'code' => '1-1100',
            'name' => 'Kas',
            'parent_code' => '1-1000',
            'category' => AccountCategory::Aset,
            'subcategory' => 'kas',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::Neraca,
            'cash_flow_category' => 'operating',
            'is_postable' => false,
            'is_system' => true,
            'display_order' => 111,
        ];

        // 1-1101 Kas Utama
        $accounts[] = [
            'code' => '1-1101',
            'name' => 'Kas Utama',
            'parent_code' => '1-1100',
            'category' => AccountCategory::Aset,
            'subcategory' => 'kas',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::Neraca,
            'cash_flow_category' => 'operating',
            'is_postable' => true,
            'is_system' => true,
            'display_order' => 112,
        ];

        // 1-1110 Bank
        $accounts[] = [
            'code' => '1-1110',
            'name' => 'Bank',
            'parent_code' => '1-1000',
            'category' => AccountCategory::Aset,
            'subcategory' => 'bank',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::Neraca,
            'cash_flow_category' => 'operating',
            'is_postable' => false,
            'is_system' => true,
            'display_order' => 120,
        ];

        // 1-1111 Bank Utama
        $accounts[] = [
            'code' => '1-1111',
            'name' => 'Bank Utama',
            'parent_code' => '1-1110',
            'category' => AccountCategory::Aset,
            'subcategory' => 'bank',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::Neraca,
            'cash_flow_category' => 'operating',
            'is_postable' => true,
            'is_system' => true,
            'display_order' => 121,
        ];

        // 1-1120 Kas dalam Perjalanan
        $accounts[] = [
            'code' => '1-1120',
            'name' => 'Kas dalam Perjalanan',
            'parent_code' => '1-1000',
            'category' => AccountCategory::Aset,
            'subcategory' => 'kas_in_transit',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::Neraca,
            'cash_flow_category' => 'operating',
            'is_postable' => true,
            'display_order' => 130,
        ];

        // 1-1200 Piutang Usaha (control)
        $accounts[] = [
            'code' => '1-1200',
            'name' => 'Piutang Usaha',
            'parent_code' => '1-1000',
            'category' => AccountCategory::Aset,
            'subcategory' => 'receivable',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => true,
            'is_control_account' => true,
            'is_system' => true,
            'display_order' => 140,
        ];

        // 1-1210 Cadangan Kerugian Piutang (contra-asset, kredit)
        $accounts[] = [
            'code' => '1-1210',
            'name' => 'Cadangan Kerugian Piutang',
            'parent_code' => '1-1000',
            'category' => AccountCategory::Aset,
            'subcategory' => 'allowance',
            'normal_balance' => NormalBalance::Kredit, // contra
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => true,
            'display_order' => 141,
        ];

        // 1-1300 Persediaan Barang (control)
        $accounts[] = [
            'code' => '1-1300',
            'name' => 'Persediaan Barang',
            'parent_code' => '1-1000',
            'category' => AccountCategory::Aset,
            'subcategory' => 'inventory',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => true,
            'is_control_account' => true,
            'is_system' => true,
            'display_order' => 150,
        ];

        // 1-1310 Persediaan Rusak
        $accounts[] = [
            'code' => '1-1310',
            'name' => 'Persediaan Rusak',
            'parent_code' => '1-1000',
            'category' => AccountCategory::Aset,
            'subcategory' => 'inventory',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => true,
            'display_order' => 151,
        ];

        // 1-1400 Uang Muka Pembelian
        $accounts[] = [
            'code' => '1-1400',
            'name' => 'Uang Muka Pembelian',
            'parent_code' => '1-1000',
            'category' => AccountCategory::Aset,
            'subcategory' => 'prepayment',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => true,
            'display_order' => 160,
        ];

        // 1-1410 Uang Muka Biaya
        $accounts[] = [
            'code' => '1-1410',
            'name' => 'Uang Muka Biaya',
            'parent_code' => '1-1000',
            'category' => AccountCategory::Aset,
            'subcategory' => 'prepayment',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => true,
            'display_order' => 161,
        ];

        // 1-1500 PPN Masukan (control)
        $accounts[] = [
            'code' => '1-1500',
            'name' => 'PPN Masukan',
            'parent_code' => '1-1000',
            'category' => AccountCategory::Aset,
            'subcategory' => 'tax',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => true,
            'is_control_account' => true,
            'is_system' => true,
            'display_order' => 170,
        ];

        // 1-1600 Beban Dibayar di Muka
        $accounts[] = [
            'code' => '1-1600',
            'name' => 'Beban Dibayar di Muka',
            'parent_code' => '1-1000',
            'category' => AccountCategory::Aset,
            'subcategory' => 'prepaid_expense',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => true,
            'display_order' => 180,
        ];

        // 1-2000 Aset Tetap
        $accounts[] = [
            'code' => '1-2000',
            'name' => 'Aset Tetap',
            'parent_code' => '1-0000',
            'category' => AccountCategory::Aset,
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => false,
            'is_system' => true,
            'display_order' => 200,
        ];

        // 1-2100 Peralatan
        $accounts[] = [
            'code' => '1-2100',
            'name' => 'Peralatan',
            'parent_code' => '1-2000',
            'category' => AccountCategory::Aset,
            'subcategory' => 'fixed_asset',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => true,
            'display_order' => 210,
        ];

        // 1-2200 Kendaraan
        $accounts[] = [
            'code' => '1-2200',
            'name' => 'Kendaraan',
            'parent_code' => '1-2000',
            'category' => AccountCategory::Aset,
            'subcategory' => 'fixed_asset',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => true,
            'display_order' => 220,
        ];

        // 1-2900 Akumulasi Penyusutan (contra-asset, kredit)
        $accounts[] = [
            'code' => '1-2900',
            'name' => 'Akumulasi Penyusutan',
            'parent_code' => '1-2000',
            'category' => AccountCategory::Aset,
            'subcategory' => 'accumulated_depreciation',
            'normal_balance' => NormalBalance::Kredit, // contra
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => true,
            'display_order' => 290,
        ];

        // ─────────────────────────────────────────────────────────────────
        // 2-0000 KEWAJIBAN
        // ─────────────────────────────────────────────────────────────────
        $accounts[] = [
            'code' => '2-0000',
            'name' => 'KEWAJIBAN',
            'category' => AccountCategory::Kewajiban,
            'normal_balance' => NormalBalance::Kredit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => false,
            'is_system' => true,
            'display_order' => 300,
        ];

        // 2-1000 Kewajiban Lancar
        $accounts[] = [
            'code' => '2-1000',
            'name' => 'Kewajiban Lancar',
            'parent_code' => '2-0000',
            'category' => AccountCategory::Kewajiban,
            'normal_balance' => NormalBalance::Kredit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => false,
            'is_system' => true,
            'display_order' => 310,
        ];

        // 2-1100 Utang Usaha (control)
        $accounts[] = [
            'code' => '2-1100',
            'name' => 'Utang Usaha',
            'parent_code' => '2-1000',
            'category' => AccountCategory::Kewajiban,
            'subcategory' => 'payable',
            'normal_balance' => NormalBalance::Kredit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => true,
            'is_control_account' => true,
            'is_system' => true,
            'display_order' => 311,
        ];

        // 2-1200 PPN Keluaran (control)
        $accounts[] = [
            'code' => '2-1200',
            'name' => 'PPN Keluaran',
            'parent_code' => '2-1000',
            'category' => AccountCategory::Kewajiban,
            'subcategory' => 'tax',
            'normal_balance' => NormalBalance::Kredit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => true,
            'is_control_account' => true,
            'is_system' => true,
            'display_order' => 320,
        ];

        // 2-1210 Utang Pajak Lainnya
        $accounts[] = [
            'code' => '2-1210',
            'name' => 'Utang Pajak Lainnya',
            'parent_code' => '2-1000',
            'category' => AccountCategory::Kewajiban,
            'subcategory' => 'tax',
            'normal_balance' => NormalBalance::Kredit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => true,
            'display_order' => 321,
        ];

        // 2-1300 Beban Masih Harus Dibayar
        $accounts[] = [
            'code' => '2-1300',
            'name' => 'Beban Masih Harus Dibayar',
            'parent_code' => '2-1000',
            'category' => AccountCategory::Kewajiban,
            'subcategory' => 'accrued_expense',
            'normal_balance' => NormalBalance::Kredit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => true,
            'display_order' => 330,
        ];

        // 2-1400 Uang Muka Pelanggan
        $accounts[] = [
            'code' => '2-1400',
            'name' => 'Uang Muka Pelanggan',
            'parent_code' => '2-1000',
            'category' => AccountCategory::Kewajiban,
            'subcategory' => 'customer_advance',
            'normal_balance' => NormalBalance::Kredit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => true,
            'display_order' => 340,
        ];

        // 2-1500 Utang kepada Pemilik
        $accounts[] = [
            'code' => '2-1500',
            'name' => 'Utang kepada Pemilik',
            'parent_code' => '2-1000',
            'category' => AccountCategory::Kewajiban,
            'subcategory' => 'owner_loan',
            'normal_balance' => NormalBalance::Kredit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => true,
            'display_order' => 350,
        ];

        // 2-2000 Kewajiban Jangka Panjang
        $accounts[] = [
            'code' => '2-2000',
            'name' => 'Kewajiban Jangka Panjang',
            'parent_code' => '2-0000',
            'category' => AccountCategory::Kewajiban,
            'normal_balance' => NormalBalance::Kredit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => false,
            'is_system' => true,
            'display_order' => 400,
        ];

        // ─────────────────────────────────────────────────────────────────
        // 3-0000 EKUITAS
        // ─────────────────────────────────────────────────────────────────
        $accounts[] = [
            'code' => '3-0000',
            'name' => 'EKUITAS',
            'category' => AccountCategory::Ekuitas,
            'normal_balance' => NormalBalance::Kredit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => false,
            'is_system' => true,
            'display_order' => 500,
        ];

        // 3-1000 Modal Pemilik
        $accounts[] = [
            'code' => '3-1000',
            'name' => 'Modal Pemilik',
            'parent_code' => '3-0000',
            'category' => AccountCategory::Ekuitas,
            'subcategory' => 'capital',
            'normal_balance' => NormalBalance::Kredit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => true,
            'is_system' => true,
            'display_order' => 510,
        ];

        // 3-2000 Prive
        $accounts[] = [
            'code' => '3-2000',
            'name' => 'Prive',
            'parent_code' => '3-0000',
            'category' => AccountCategory::Ekuitas,
            'subcategory' => 'drawing',
            'normal_balance' => NormalBalance::Debit, // contra equity
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => true,
            'display_order' => 520,
        ];

        // 3-3000 Laba Ditahan
        $accounts[] = [
            'code' => '3-3000',
            'name' => 'Laba Ditahan',
            'parent_code' => '3-0000',
            'category' => AccountCategory::Ekuitas,
            'subcategory' => 'retained_earnings',
            'normal_balance' => NormalBalance::Kredit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => true,
            'is_system' => true,
            'display_order' => 530,
        ];

        // 3-4000 Laba/Rugi Berjalan
        $accounts[] = [
            'code' => '3-4000',
            'name' => 'Laba/Rugi Berjalan',
            'parent_code' => '3-0000',
            'category' => AccountCategory::Ekuitas,
            'subcategory' => 'current_earnings',
            'normal_balance' => NormalBalance::Kredit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => true,
            'is_system' => true,
            'display_order' => 540,
        ];

        // 3-5000 Saldo Pembukaan
        $accounts[] = [
            'code' => '3-5000',
            'name' => 'Saldo Pembukaan',
            'parent_code' => '3-0000',
            'category' => AccountCategory::Ekuitas,
            'subcategory' => 'opening_balance',
            'normal_balance' => NormalBalance::Kredit,
            'statement_classification' => StatementClassification::Neraca,
            'is_postable' => true,
            'is_system' => true,
            'display_order' => 550,
        ];

        // ─────────────────────────────────────────────────────────────────
        // 4-0000 PENDAPATAN
        // ─────────────────────────────────────────────────────────────────
        $accounts[] = [
            'code' => '4-0000',
            'name' => 'PENDAPATAN',
            'category' => AccountCategory::Pendapatan,
            'normal_balance' => NormalBalance::Kredit,
            'statement_classification' => StatementClassification::LabaRugi,
            'is_postable' => false,
            'is_system' => true,
            'display_order' => 600,
        ];

        // 4-1100 Penjualan Retail
        $accounts[] = [
            'code' => '4-1100',
            'name' => 'Penjualan Retail',
            'parent_code' => '4-0000',
            'category' => AccountCategory::Pendapatan,
            'subcategory' => 'sales_retail',
            'normal_balance' => NormalBalance::Kredit,
            'statement_classification' => StatementClassification::LabaRugi,
            'cash_flow_category' => 'operating',
            'is_postable' => true,
            'is_system' => true,
            'display_order' => 610,
        ];

        // 4-1200 Penjualan Grosir
        $accounts[] = [
            'code' => '4-1200',
            'name' => 'Penjualan Grosir',
            'parent_code' => '4-0000',
            'category' => AccountCategory::Pendapatan,
            'subcategory' => 'sales_wholesale',
            'normal_balance' => NormalBalance::Kredit,
            'statement_classification' => StatementClassification::LabaRugi,
            'cash_flow_category' => 'operating',
            'is_postable' => true,
            'is_system' => true,
            'display_order' => 620,
        ];

        // 4-1300 Pendapatan Lainnya
        $accounts[] = [
            'code' => '4-1300',
            'name' => 'Pendapatan Lainnya',
            'parent_code' => '4-0000',
            'category' => AccountCategory::Pendapatan,
            'subcategory' => 'other_income',
            'normal_balance' => NormalBalance::Kredit,
            'statement_classification' => StatementClassification::LabaRugi,
            'is_postable' => true,
            'display_order' => 630,
        ];

        // 4-1900 Retur Penjualan (contra-revenue, debit)
        $accounts[] = [
            'code' => '4-1900',
            'name' => 'Retur Penjualan',
            'parent_code' => '4-0000',
            'category' => AccountCategory::Pendapatan,
            'subcategory' => 'sales_return',
            'normal_balance' => NormalBalance::Debit, // contra
            'statement_classification' => StatementClassification::LabaRugi,
            'is_postable' => true,
            'is_system' => true,
            'display_order' => 690,
        ];

        // 4-1910 Diskon Penjualan (contra-revenue, debit)
        $accounts[] = [
            'code' => '4-1910',
            'name' => 'Diskon Penjualan',
            'parent_code' => '4-0000',
            'category' => AccountCategory::Pendapatan,
            'subcategory' => 'sales_discount',
            'normal_balance' => NormalBalance::Debit, // contra
            'statement_classification' => StatementClassification::LabaRugi,
            'is_postable' => true,
            'is_system' => true,
            'display_order' => 691,
        ];

        // ─────────────────────────────────────────────────────────────────
        // 5-0000 HARGA POKOK PENJUALAN
        // ─────────────────────────────────────────────────────────────────
        $accounts[] = [
            'code' => '5-0000',
            'name' => 'HARGA POKOK PENJUALAN',
            'category' => AccountCategory::Hpp,
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::LabaRugi,
            'is_postable' => false,
            'is_system' => true,
            'display_order' => 700,
        ];

        // 5-1100 HPP Retail
        $accounts[] = [
            'code' => '5-1100',
            'name' => 'HPP Retail',
            'parent_code' => '5-0000',
            'category' => AccountCategory::Hpp,
            'subcategory' => 'cogs_retail',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::LabaRugi,
            'is_postable' => true,
            'is_system' => true,
            'display_order' => 710,
        ];

        // 5-1200 HPP Grosir
        $accounts[] = [
            'code' => '5-1200',
            'name' => 'HPP Grosir',
            'parent_code' => '5-0000',
            'category' => AccountCategory::Hpp,
            'subcategory' => 'cogs_wholesale',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::LabaRugi,
            'is_postable' => true,
            'is_system' => true,
            'display_order' => 720,
        ];

        // 5-1300 Selisih Stok
        $accounts[] = [
            'code' => '5-1300',
            'name' => 'Selisih Stok',
            'parent_code' => '5-0000',
            'category' => AccountCategory::Hpp,
            'subcategory' => 'stock_variance',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::LabaRugi,
            'is_postable' => true,
            'display_order' => 730,
        ];

        // 5-1400 Barang Rusak/Kedaluwarsa
        $accounts[] = [
            'code' => '5-1400',
            'name' => 'Barang Rusak/Kedaluwarsa',
            'parent_code' => '5-0000',
            'category' => AccountCategory::Hpp,
            'subcategory' => 'damaged_expired',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::LabaRugi,
            'is_postable' => true,
            'display_order' => 740,
        ];

        // 5-1500 Koreksi Nilai Persediaan
        $accounts[] = [
            'code' => '5-1500',
            'name' => 'Koreksi Nilai Persediaan',
            'parent_code' => '5-0000',
            'category' => AccountCategory::Hpp,
            'subcategory' => 'inventory_adjustment',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::LabaRugi,
            'is_postable' => true,
            'display_order' => 750,
        ];

        // ─────────────────────────────────────────────────────────────────
        // 6-0000 BEBAN OPERASIONAL
        // ─────────────────────────────────────────────────────────────────
        $accounts[] = [
            'code' => '6-0000',
            'name' => 'BEBAN OPERASIONAL',
            'category' => AccountCategory::Beban,
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::LabaRugi,
            'is_postable' => false,
            'is_system' => true,
            'display_order' => 800,
        ];

        // 6-1100 Beban Gaji
        $accounts[] = [
            'code' => '6-1100',
            'name' => 'Beban Gaji',
            'parent_code' => '6-0000',
            'category' => AccountCategory::Beban,
            'subcategory' => 'salary',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::LabaRugi,
            'cash_flow_category' => 'operating',
            'is_postable' => true,
            'display_order' => 810,
        ];

        // 6-1200 Beban Transportasi
        $accounts[] = [
            'code' => '6-1200',
            'name' => 'Beban Transportasi',
            'parent_code' => '6-0000',
            'category' => AccountCategory::Beban,
            'subcategory' => 'transport',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::LabaRugi,
            'cash_flow_category' => 'operating',
            'is_postable' => true,
            'display_order' => 820,
        ];

        // 6-1300 Beban Sewa
        $accounts[] = [
            'code' => '6-1300',
            'name' => 'Beban Sewa',
            'parent_code' => '6-0000',
            'category' => AccountCategory::Beban,
            'subcategory' => 'rent',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::LabaRugi,
            'cash_flow_category' => 'operating',
            'is_postable' => true,
            'display_order' => 830,
        ];

        // 6-1400 Beban Listrik
        $accounts[] = [
            'code' => '6-1400',
            'name' => 'Beban Listrik',
            'parent_code' => '6-0000',
            'category' => AccountCategory::Beban,
            'subcategory' => 'utilities',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::LabaRugi,
            'cash_flow_category' => 'operating',
            'is_postable' => true,
            'display_order' => 840,
        ];

        // 6-1500 Beban Administrasi
        $accounts[] = [
            'code' => '6-1500',
            'name' => 'Beban Administrasi',
            'parent_code' => '6-0000',
            'category' => AccountCategory::Beban,
            'subcategory' => 'administration',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::LabaRugi,
            'cash_flow_category' => 'operating',
            'is_postable' => true,
            'display_order' => 850,
        ];

        // 6-1900 Beban Lainnya
        $accounts[] = [
            'code' => '6-1900',
            'name' => 'Beban Lainnya',
            'parent_code' => '6-0000',
            'category' => AccountCategory::Beban,
            'subcategory' => 'other_expense',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::LabaRugi,
            'is_postable' => true,
            'display_order' => 890,
        ];

        // ─────────────────────────────────────────────────────────────────
        // 7-1000 PENDAPATAN LAINNYA (root heading)
        // ─────────────────────────────────────────────────────────────────
        $accounts[] = [
            'code' => '7-1000',
            'name' => 'PENDAPATAN LAINNYA',
            'category' => AccountCategory::PendapatanLainnya,
            'normal_balance' => NormalBalance::Kredit,
            'statement_classification' => StatementClassification::LabaRugi,
            'is_postable' => false,
            'is_system' => true,
            'display_order' => 900,
        ];

        // 7-1100 Pendapatan Bunga
        $accounts[] = [
            'code' => '7-1100',
            'name' => 'Pendapatan Bunga',
            'parent_code' => '7-1000',
            'category' => AccountCategory::PendapatanLainnya,
            'subcategory' => 'interest_income',
            'normal_balance' => NormalBalance::Kredit,
            'statement_classification' => StatementClassification::LabaRugi,
            'is_postable' => true,
            'display_order' => 901,
        ];

        // ─────────────────────────────────────────────────────────────────
        // 7-2000 BEBAN LAINNYA (root heading)
        // ─────────────────────────────────────────────────────────────────
        $accounts[] = [
            'code' => '7-2000',
            'name' => 'BEBAN LAINNYA',
            'category' => AccountCategory::BebanLainnya,
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::LabaRugi,
            'is_postable' => false,
            'is_system' => true,
            'display_order' => 920,
        ];

        // 7-2100 Beban Bank
        $accounts[] = [
            'code' => '7-2100',
            'name' => 'Beban Bank',
            'parent_code' => '7-2000',
            'category' => AccountCategory::BebanLainnya,
            'subcategory' => 'bank_fee',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::LabaRugi,
            'is_postable' => true,
            'display_order' => 921,
        ];

        // 7-2200 Beban Bunga
        $accounts[] = [
            'code' => '7-2200',
            'name' => 'Beban Bunga',
            'parent_code' => '7-2000',
            'category' => AccountCategory::BebanLainnya,
            'subcategory' => 'interest_expense',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::LabaRugi,
            'is_postable' => true,
            'display_order' => 922,
        ];

        // 7-2900 Selisih Pembulatan
        $accounts[] = [
            'code' => '7-2900',
            'name' => 'Selisih Pembulatan',
            'parent_code' => '7-2000',
            'category' => AccountCategory::BebanLainnya,
            'subcategory' => 'rounding',
            'normal_balance' => NormalBalance::Debit,
            'statement_classification' => StatementClassification::LabaRugi,
            'is_postable' => true,
            'is_system' => true,
            'display_order' => 929,
        ];

        return $accounts;
    }
}
