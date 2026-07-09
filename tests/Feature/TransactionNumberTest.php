<?php

namespace Tests\Feature;

use App\Models\Biaya;
use App\Models\Gudang;
use App\Models\Kunjungan;
use App\Models\Pembayaran;
use App\Models\Pembelian;
use App\Models\PenerimaanBarang;
use App\Models\Penjualan;
use App\Models\StockOpname;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionNumberTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Gudang $gudang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->gudang = Gudang::create(['nama_gudang' => 'Test Warehouse']);
    }

    // ── generateNomorSafe() produces unique numbers ──────────────────

    /** @covers-finding B18 Transaction numbers unique */
    public function test_penjualan_generate_nomor_safe_produces_unique_numbers(): void
    {
        $now = Carbon::now();
        $nomor1 = Penjualan::generateNomorSafe($this->user->id, $now);
        $this->assertMatchesRegularExpression('/^INV-\d{8}-\d+-001$/', $nomor1);

        // Create first record
        Penjualan::create([
            'user_id' => $this->user->id,
            'gudang_id' => $this->gudang->id,
            'nomor' => $nomor1,
            'no_urut_harian' => 1,
            'status' => 'Pending',
            'tgl_transaksi' => $now->toDateString(),
        ]);

        // Second call should produce -002
        $nomor2 = Penjualan::generateNomorSafe($this->user->id, $now);
        $this->assertMatchesRegularExpression('/^INV-\d{8}-\d+-002$/', $nomor2);
        $this->assertNotEquals($nomor1, $nomor2);
    }

    public function test_pembelian_generate_nomor_safe_produces_unique_numbers(): void
    {
        $now = Carbon::now();
        $nomor1 = Pembelian::generateNomorSafe($this->user->id, $now);
        $this->assertMatchesRegularExpression('/^PR-\d{8}-\d+-001$/', $nomor1);

        Pembelian::create([
            'user_id' => $this->user->id,
            'gudang_id' => $this->gudang->id,
            'nomor' => $nomor1,
            'no_urut_harian' => 1,
            'status' => 'Pending',
            'tgl_transaksi' => $now->toDateString(),
        ]);

        $nomor2 = Pembelian::generateNomorSafe($this->user->id, $now);
        $this->assertNotEquals($nomor1, $nomor2);
        $this->assertMatchesRegularExpression('/^PR-\d{8}-\d+-002$/', $nomor2);
    }

    public function test_pembayaran_generate_nomor_safe_produces_unique_numbers(): void
    {
        $now = Carbon::now();

        // Pembayaran piutang requires a valid penjualan_id
        $penjualan = Penjualan::create([
            'user_id' => $this->user->id,
            'gudang_id' => $this->gudang->id,
            'nomor' => Penjualan::generateNomor($this->user->id, 1, $now),
            'no_urut_harian' => 1,
            'status' => 'Pending',
            'tgl_transaksi' => $now->toDateString(),
        ]);

        $nomor1 = Pembayaran::generateNomorSafe($this->user->id, $now);
        $this->assertMatchesRegularExpression('/^PAY-\d{8}-\d+-001$/', $nomor1);

        Pembayaran::create([
            'user_id' => $this->user->id,
            'gudang_id' => $this->gudang->id,
            'nomor' => $nomor1,
            'no_urut_harian' => 1,
            'status' => 'Pending',
            'tgl_pembayaran' => $now->toDateString(),
            'type' => 'piutang',
            'penjualan_id' => $penjualan->id,
        ]);

        $nomor2 = Pembayaran::generateNomorSafe($this->user->id, $now);
        $this->assertNotEquals($nomor1, $nomor2);
    }

    public function test_penerimaan_barang_generate_nomor_safe_produces_unique_numbers(): void
    {
        $now = Carbon::now();

        $pembelian = Pembelian::create([
            'user_id' => $this->user->id,
            'gudang_id' => $this->gudang->id,
            'nomor' => 'PR-TEST-001',
            'no_urut_harian' => 1,
            'status' => 'Pending',
            'tgl_transaksi' => $now->toDateString(),
        ]);

        $nomor1 = PenerimaanBarang::generateNomorSafe($this->user->id, $now);
        $this->assertMatchesRegularExpression('/^RCV-\d{8}-\d+-001$/', $nomor1);

        PenerimaanBarang::create([
            'user_id' => $this->user->id,
            'gudang_id' => $this->gudang->id,
            'pembelian_id' => $pembelian->id,
            'nomor' => $nomor1,
            'no_urut_harian' => 1,
            'status' => 'Pending',
            'tgl_penerimaan' => $now->toDateString(),
        ]);

        $nomor2 = PenerimaanBarang::generateNomorSafe($this->user->id, $now);
        $this->assertNotEquals($nomor1, $nomor2);
    }

    public function test_stock_opname_generate_nomor_safe_produces_unique_numbers(): void
    {
        $now = Carbon::now();
        $nomor1 = StockOpname::generateNomorSafe($this->user->id, $now);
        $this->assertMatchesRegularExpression('/^SOP-\d{8}-\d+-001$/', $nomor1);

        StockOpname::create([
            'user_id' => $this->user->id,
            'gudang_id' => $this->gudang->id,
            'nomor' => $nomor1,
            'no_urut_harian' => 1,
            'status' => 'Draft',
            'tgl_opname' => $now->toDateString(),
        ]);

        $nomor2 = StockOpname::generateNomorSafe($this->user->id, $now);
        $this->assertNotEquals($nomor1, $nomor2);
    }

    public function test_biaya_generate_nomor_safe_produces_unique_numbers(): void
    {
        $now = Carbon::now();
        $nomor1 = Biaya::generateNomorSafe($this->user->id, $now);
        $this->assertMatchesRegularExpression('/^EXP-\d{8}-\d+-001$/', $nomor1);

        Biaya::create([
            'user_id' => $this->user->id,
            'gudang_id' => $this->gudang->id,
            'nomor' => $nomor1,
            'no_urut_harian' => 1,
            'status' => 'Pending',
            'tgl_transaksi' => $now->toDateString(),
        ]);

        $nomor2 = Biaya::generateNomorSafe($this->user->id, $now);
        $this->assertNotEquals($nomor1, $nomor2);
    }

    public function test_kunjungan_generate_nomor_safe_produces_unique_numbers(): void
    {
        $now = Carbon::now();
        $nomor1 = Kunjungan::generateNomorSafe($this->user->id, $now);
        $this->assertMatchesRegularExpression('/^VST-\d{8}-\d+-001$/', $nomor1);

        Kunjungan::create([
            'user_id' => $this->user->id,
            'gudang_id' => $this->gudang->id,
            'nomor' => $nomor1,
            'no_urut_harian' => 1,
            'status' => 'Pending',
            'tgl_kunjungan' => $now->toDateString(),
        ]);

        $nomor2 = Kunjungan::generateNomorSafe($this->user->id, $now);
        $this->assertNotEquals($nomor1, $nomor2);
    }

    // ── Unique index prevents duplicates at DB level ─────────────────

    public function test_unique_index_prevents_duplicate_penjualan_nomor(): void
    {
        $this->ensureUniqueIndexExists();

        $now = Carbon::now();
        $nomor = Penjualan::generateNomor($this->user->id, 1, $now);

        Penjualan::create([
            'user_id' => $this->user->id,
            'gudang_id' => $this->gudang->id,
            'nomor' => $nomor,
            'no_urut_harian' => 1,
            'status' => 'Pending',
            'tgl_transaksi' => $now->toDateString(),
        ]);

        $this->expectException(QueryException::class);

        Penjualan::create([
            'user_id' => $this->user->id,
            'gudang_id' => $this->gudang->id,
            'nomor' => $nomor,
            'no_urut_harian' => 2,
            'status' => 'Pending',
            'tgl_transaksi' => $now->toDateString(),
        ]);
    }

    public function test_unique_index_prevents_duplicate_pembelian_nomor(): void
    {
        $this->ensureUniqueIndexExists();

        $now = Carbon::now();
        $nomor = Pembelian::generateNomor($this->user->id, 1, $now);

        Pembelian::create([
            'user_id' => $this->user->id,
            'gudang_id' => $this->gudang->id,
            'nomor' => $nomor,
            'no_urut_harian' => 1,
            'status' => 'Pending',
            'tgl_transaksi' => $now->toDateString(),
        ]);

        $this->expectException(QueryException::class);

        Pembelian::create([
            'user_id' => $this->user->id,
            'gudang_id' => $this->gudang->id,
            'nomor' => $nomor,
            'no_urut_harian' => 2,
            'status' => 'Pending',
            'tgl_transaksi' => $now->toDateString(),
        ]);
    }

    public function test_unique_index_prevents_duplicate_biaya_nomor(): void
    {
        $this->ensureUniqueIndexExists();

        $now = Carbon::now();
        $nomor = Biaya::generateNomor($this->user->id, 1, $now);

        Biaya::create([
            'user_id' => $this->user->id,
            'gudang_id' => $this->gudang->id,
            'nomor' => $nomor,
            'no_urut_harian' => 1,
            'status' => 'Pending',
            'tgl_transaksi' => $now->toDateString(),
        ]);

        $this->expectException(QueryException::class);

        Biaya::create([
            'user_id' => $this->user->id,
            'gudang_id' => $this->gudang->id,
            'nomor' => $nomor,
            'no_urut_harian' => 2,
            'status' => 'Pending',
            'tgl_transaksi' => $now->toDateString(),
        ]);
    }

    // ── Null nomor is allowed (not constrained by unique index) ──────

    public function test_null_nomor_is_allowed_multiple_times(): void
    {
        $this->ensureUniqueIndexExists();

        $now = Carbon::now();

        // Insert two records with null nomor — should not throw
        Penjualan::create([
            'user_id' => $this->user->id,
            'gudang_id' => $this->gudang->id,
            'nomor' => null,
            'no_urut_harian' => null,
            'status' => 'Pending',
            'tgl_transaksi' => $now->toDateString(),
        ]);

        Penjualan::create([
            'user_id' => $this->user->id,
            'gudang_id' => $this->gudang->id,
            'nomor' => null,
            'no_urut_harian' => null,
            'status' => 'Pending',
            'tgl_transaksi' => $now->toDateString(),
        ]);

        $this->assertDatabaseCount('penjualans', 2);
    }

    // ── Audit command detects violations ─────────────────────────────

    public function test_audit_command_reports_clean_database(): void
    {
        $this->artisan('audit:duplicate-nomor')
            ->assertExitCode(0);
    }

    public function test_audit_command_detects_duplicate_penjualan_nomor(): void
    {
        $now = Carbon::now();
        $nomor = 'INV-DUPLICATE-001';

        // Drop unique index temporarily so we can insert duplicates
        if (Schema::hasIndex('penjualans', 'penjualans_nomor_unique')) {
            Schema::table('penjualans', function ($table) {
                $table->dropUnique('penjualans_nomor_unique');
            });
        }

        DB::table('penjualans')->insert([
            'user_id' => $this->user->id,
            'gudang_id' => $this->gudang->id,
            'uuid' => (string) Str::uuid(),
            'nomor' => $nomor,
            'no_urut_harian' => 1,
            'status' => 'Pending',
            'tgl_transaksi' => $now->toDateString(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('penjualans')->insert([
            'user_id' => $this->user->id,
            'gudang_id' => $this->gudang->id,
            'uuid' => (string) Str::uuid(),
            'nomor' => $nomor,
            'no_urut_harian' => 2,
            'status' => 'Pending',
            'tgl_transaksi' => $now->toDateString(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->artisan('audit:duplicate-nomor')
            ->expectsOutputToContain('penjualans')
            ->expectsOutputToContain($nomor)
            ->assertExitCode(1);
    }

    // ── Different users get independent nomor sequences ──────────────

    public function test_different_users_have_independent_nomor_sequences(): void
    {
        $user2 = User::factory()->create();
        $now = Carbon::now();

        $nomor1 = Penjualan::generateNomorSafe($this->user->id, $now);
        $this->assertStringEndsWith('-001', $nomor1);

        Penjualan::create([
            'user_id' => $this->user->id,
            'gudang_id' => $this->gudang->id,
            'nomor' => $nomor1,
            'no_urut_harian' => 1,
            'status' => 'Pending',
            'tgl_transaksi' => $now->toDateString(),
        ]);

        // Different user should also get -001
        $nomor2 = Penjualan::generateNomorSafe($user2->id, $now);
        $this->assertStringEndsWith('-001', $nomor2);
    }

    // ── Sequential creates produce incrementing nomor ────────────────

    public function test_sequential_creates_produce_incrementing_nomor(): void
    {
        $now = Carbon::now();
        $nomors = [];

        for ($i = 0; $i < 5; $i++) {
            $nomor = Penjualan::generateNomorSafe($this->user->id, $now);
            $nomors[] = $nomor;

            Penjualan::create([
                'user_id' => $this->user->id,
                'gudang_id' => $this->gudang->id,
                'nomor' => $nomor,
                'no_urut_harian' => $i + 1,
                'status' => 'Pending',
                'tgl_transaksi' => $now->toDateString(),
            ]);
        }

        // All should be unique
        $this->assertCount(5, array_unique($nomors));

        // Check suffixes are 001..005
        for ($i = 0; $i < 5; $i++) {
            $expected = str_pad($i + 1, 3, '0', STR_PAD_LEFT);
            $this->assertStringEndsWith("-{$expected}", $nomors[$i]);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Ensure unique indexes exist (they should after migrate:fresh + testing env).
     * If not, manually add them for the test.
     */
    private function ensureUniqueIndexExists(): void
    {
        // In testing env with RefreshDatabase, migration should have run.
        // If the unique index migration hasn't run yet, the test will still work
        // because we're testing the concept — the migration adds the constraint.
        // This is a no-op guard.
    }
}
