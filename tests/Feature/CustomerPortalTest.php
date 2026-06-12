<?php

namespace Tests\Feature;

use App\Models\Kontak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_customer_phone_check_accepts_legacy_and_normalized_phone_formats(): void
    {
        $this->post(route('customer.check.phone'), ['no_telp' => '+62 811-1111-1111'])
            ->assertOk()
            ->assertViewIs('customer.pin')
            ->assertViewHas('no_telp', '6281111111111')
            ->assertSee('Toko Melati');
    }

    public function test_customer_can_login_with_normalized_phone_and_pin(): void
    {
        $kontak = Kontak::where('nama', 'Toko Melati')->firstOrFail();

        $this->post(route('customer.login.submit'), [
            'no_telp' => '6281111111111',
            'pin' => '123456',
        ])
            ->assertRedirect(route('customer.dashboard'))
            ->assertSessionHas('customer_id', $kontak->id)
            ->assertSessionHas('customer_no_telp', $kontak->no_telp)
            ->assertSessionHas('customer_nama', $kontak->nama);

        $this->withSession(['customer_id' => $kontak->id])
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Toko Melati');
    }
}
