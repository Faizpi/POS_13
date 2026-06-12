<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produks', function (Blueprint $table) {
            $table->id();
            $table->string('nama_produk')->index();
            $table->string('item_code')->nullable()->unique();
            $table->decimal('harga', 15, 2)->default(0);
            $table->decimal('harga_grosir', 15, 2)->default(0);
            $table->string('satuan')->default('Pcs');
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produks');
    }
};
