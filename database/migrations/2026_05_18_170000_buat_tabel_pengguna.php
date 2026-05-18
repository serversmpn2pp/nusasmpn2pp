<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menjalankan migration.
     */
    public function up(): void
    {
        Schema::create('pengguna', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')
                ->nullable()
                ->constrained('pegawai')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('nama');
            $table->string('username')->unique();
            $table->string('kata_sandi');
            $table->string('peran', 50)->default('administrator')->index();
            $table->boolean('aktif')->default(true);
            $table->boolean('akun_sistem')->default(false);
            $table->timestamp('terakhir_login_pada')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        DB::table('pengguna')->insert([
            'nama' => 'Administrator NUSA',
            'username' => 'administrator',
            'kata_sandi' => Hash::make('administrator'),
            'peran' => 'administrator',
            'aktif' => true,
            'akun_sistem' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Membatalkan migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengguna');
    }
};
