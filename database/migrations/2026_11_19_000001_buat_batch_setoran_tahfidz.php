<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_setoran_tahfidz', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pertemuan_tahfidz_id')->constrained('pertemuan_tahfidz')->cascadeOnDelete();
            $table->foreignId('penilai_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->uuid('token');
            $table->char('sidik', 64);
            $table->unsignedInteger('jumlah');
            $table->timestamp('created_at');
            $table->unique(['pertemuan_tahfidz_id', 'token'], 'batch_setoran_tahfidz_token_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_setoran_tahfidz');
    }
};
