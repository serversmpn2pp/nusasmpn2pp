<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menjalankan migration.
     */
    public function up(): void
    {
        Schema::create('skema_bobot_nilai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_pelajaran_id')
                ->constrained('tahun_pelajaran')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('semester', 20);
            $table->unsignedSmallInteger('tingkat')->nullable();
            $table->unsignedSmallInteger('bobot_formatif')->default(35);
            $table->unsignedSmallInteger('bobot_sumatif')->default(25);
            $table->unsignedSmallInteger('bobot_sts')->default(15);
            $table->unsignedSmallInteger('bobot_sas_saj')->default(25);
            $table->boolean('aktif')->default(true);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['tahun_pelajaran_id', 'semester', 'tingkat'], 'skema_bobot_scope_unik');
            $table->index(['aktif', 'semester']);
        });
    }

    /**
     * Membatalkan migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('skema_bobot_nilai');
    }
};
