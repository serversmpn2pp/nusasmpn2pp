<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periode_berhalangan_ibadah', function (Blueprint $table) {
            $table->timestamp('terakhir_dikonfirmasi_pada')->nullable();
            $table->foreignId('terakhir_dikonfirmasi_oleh_pengguna_id')
                ->nullable()
                ->constrained('pengguna')
                ->nullOnDelete();
            $table->date('konfirmasi_berikutnya_pada')->nullable();

            $table->index(
                ['status', 'konfirmasi_berikutnya_pada'],
                'periode_berhalangan_konfirmasi_berikutnya_idx'
            );
        });

        Schema::create('konfirmasi_berhalangan_ibadah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_berhalangan_ibadah_id')
                ->constrained('periode_berhalangan_ibadah')
                ->cascadeOnDelete();
            $table->foreignId('dikonfirmasi_oleh_pengguna_id')
                ->nullable()
                ->constrained('pengguna')
                ->nullOnDelete();
            $table->string('hasil', 30);
            $table->timestamp('dikonfirmasi_pada');
            $table->date('konfirmasi_berikutnya_pada')->nullable();
            $table->text('catatan_privat')->nullable();
            $table->timestamps();

            $table->index(
                ['periode_berhalangan_ibadah_id', 'dikonfirmasi_pada'],
                'konfirmasi_berhalangan_periode_waktu_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('konfirmasi_berhalangan_ibadah');

        Schema::table('periode_berhalangan_ibadah', function (Blueprint $table) {
            $table->dropIndex('periode_berhalangan_konfirmasi_berikutnya_idx');
            $table->dropConstrainedForeignId('terakhir_dikonfirmasi_oleh_pengguna_id');
            $table->dropColumn([
                'terakhir_dikonfirmasi_pada',
                'konfirmasi_berikutnya_pada',
            ]);
        });
    }
};
