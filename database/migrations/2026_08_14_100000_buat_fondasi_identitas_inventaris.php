<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_inventaris', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique()->default('utama');
            $table->string('awalan_nomor_aset', 80)->default('12.03.15.08.10');
            $table->string('akhiran_nomor_aset', 20)->default('08');
            $table->string('nama_pemilik', 160)->default('SMPN 2 Padang Panjang');
            $table->unsignedSmallInteger('jumlah_digit_id_internal')->default(6);
            $table->foreignId('diperbarui_oleh_pengguna_id')
                ->nullable()
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();
        });

        Schema::create('sumber_perolehan_barang', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->string('nama', 120)->unique();
            $table->text('deskripsi')->nullable();
            $table->boolean('aktif')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('urutan_kode_inventaris', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 40);
            $table->unsignedSmallInteger('tahun')->default(0);
            $table->unsignedBigInteger('nomor_terakhir')->default(0);
            $table->timestamps();

            $table->unique(['jenis', 'tahun']);
        });

        DB::table('pengaturan_inventaris')->insert([
            'kode' => 'utama',
            'awalan_nomor_aset' => '12.03.15.08.10',
            'akhiran_nomor_aset' => '08',
            'nama_pemilik' => 'SMPN 2 Padang Panjang',
            'jumlah_digit_id_internal' => 6,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sumber_perolehan_barang')->insert([
            [
                'kode' => 'BOS',
                'nama' => 'BOS',
                'deskripsi' => 'Barang yang diperoleh melalui dana Bantuan Operasional Sekolah.',
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode' => 'DAK',
                'nama' => 'DAK',
                'deskripsi' => 'Barang yang diperoleh melalui Dana Alokasi Khusus.',
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('urutan_kode_inventaris');
        Schema::dropIfExists('sumber_perolehan_barang');
        Schema::dropIfExists('pengaturan_inventaris');
    }
};
