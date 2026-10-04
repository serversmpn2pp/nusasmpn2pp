<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaduan_humas', function (Blueprint $table) {
            $table->foreignId('pelapor_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->boolean('rahasiakan_identitas')->default(false);
            $table->index(['pelapor_pengguna_id', 'status']);
        });
        Schema::table('lampiran_pengaduan_humas', function (Blueprint $table) {
            $table->string('asal', 20)->default('internal');
        });
        Schema::create('pesan_pengaduan_humas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengaduan_humas_id')->constrained('pengaduan_humas')->cascadeOnDelete();
            $table->uuid('token_pengiriman')->unique();
            $table->string('asal', 20);
            $table->text('isi');
            $table->foreignId('pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['pengaduan_humas_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pesan_pengaduan_humas');
        Schema::table('lampiran_pengaduan_humas', fn (Blueprint $table) => $table->dropColumn('asal'));
        Schema::table('pengaduan_humas', function (Blueprint $table) {
            $table->dropIndex(['pelapor_pengguna_id', 'status']);
            $table->dropConstrainedForeignId('pelapor_pengguna_id');
            $table->dropColumn('rahasiakan_identitas');
        });
    }
};
