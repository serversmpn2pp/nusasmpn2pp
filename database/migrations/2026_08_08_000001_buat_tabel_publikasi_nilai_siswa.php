<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publikasi_nilai_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_mata_pelajaran_id')
                ->constrained('guru_mata_pelajaran')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('semester', 20);
            $table->boolean('dipublikasikan')->default(false);
            $table->timestamp('dipublikasikan_pada')->nullable();
            $table->foreignId('dipublikasikan_oleh_pengguna_id')
                ->nullable()
                ->constrained('pengguna')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['guru_mata_pelajaran_id', 'semester'],
                'publikasi_nilai_guru_mapel_semester_unik',
            );
            $table->index(
                ['dipublikasikan', 'dipublikasikan_pada'],
                'publikasi_nilai_status_tanggal',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publikasi_nilai_siswa');
    }
};
