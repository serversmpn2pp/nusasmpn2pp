<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const KODE_IZIN = 'aktivitas_login.lihat';

    public function up(): void
    {
        Schema::create('riwayat_login', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengguna_id')
                ->nullable()
                ->constrained('pengguna')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('username');
            $table->boolean('berhasil')->default(false);
            $table->string('alamat_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['pengguna_id', 'created_at'], 'riwayat_login_pengguna_waktu_idx');
            $table->index(['berhasil', 'created_at'], 'riwayat_login_status_waktu_idx');
            $table->index(['username', 'created_at'], 'riwayat_login_username_waktu_idx');
        });

        $waktu = now();

        DB::table('izin')->updateOrInsert(
            ['kode' => self::KODE_IZIN],
            [
                'kelompok' => 'Keamanan',
                'nama' => 'Lihat aktivitas login',
                'deskripsi' => 'Melihat login terakhir serta riwayat login berhasil dan gagal pengguna NUSA.',
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
        );

        $peranId = DB::table('peran')->where('kode', 'administrator')->value('id');
        $izinId = DB::table('izin')->where('kode', self::KODE_IZIN)->value('id');

        if ($peranId && $izinId) {
            DB::table('peran_izin')->insertOrIgnore([
                'peran_id' => $peranId,
                'izin_id' => $izinId,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ]);
        }
    }

    public function down(): void
    {
        $izinId = DB::table('izin')->where('kode', self::KODE_IZIN)->value('id');

        if ($izinId) {
            DB::table('peran_izin')->where('izin_id', $izinId)->delete();
            DB::table('izin')->where('id', $izinId)->delete();
        }

        Schema::dropIfExists('riwayat_login');
    }
};
