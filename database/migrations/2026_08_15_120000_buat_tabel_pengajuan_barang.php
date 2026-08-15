<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_barang', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_pengajuan', 50)->unique();
            $table->foreignId('pegawai_id')
                ->constrained('pegawai')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('barang_id')
                ->constrained('barang')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('jenis_pengajuan', 30)->index();
            $table->decimal('jumlah', 14, 2);
            $table->date('tanggal_pengajuan')->index();
            $table->date('tanggal_dibutuhkan')->index();
            $table->date('rencana_kembali')->nullable()->index();
            $table->text('tujuan');
            $table->string('status', 30)->default('menunggu')->index();
            $table->text('catatan_petugas')->nullable();
            $table->foreignId('diproses_oleh_pengguna_id')
                ->nullable()
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('diproses_pada')->nullable();
            $table->foreignId('peminjaman_barang_id')
                ->nullable()
                ->constrained('peminjaman_barang')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();

            $table->index(['pegawai_id', 'status']);
            $table->index(['barang_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_barang');
    }
};
