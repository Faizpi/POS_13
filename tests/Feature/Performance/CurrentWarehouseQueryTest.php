<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Models\Gudang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CurrentWarehouseQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_warehouse_is_loaded_only_once_per_user_instance(): void
    {
        $warehouse = Gudang::query()->create([
            'nama_gudang' => 'Gudang Query Budget',
            'alamat_gudang' => 'Test',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'role' => 'admin',
            'current_gudang_id' => $warehouse->id,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->assertSame($warehouse->id, $user->getCurrentGudang()?->id);
        $this->assertSame($warehouse->id, $user->getCurrentGudang()?->id);
        $this->assertSame($warehouse->id, $user->getCurrentGudang()?->id);

        $warehouseQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains(strtolower($query['query']), 'gudangs'));

        $this->assertCount(1, $warehouseQueries);
    }
}
