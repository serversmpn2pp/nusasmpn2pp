<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survei_pembelajaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_mata_pelajaran_id')
                ->constrained('guru_mata_pelajaran')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('siswa_id')
                ->constrained('siswa')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('semester', 20);
            $table->unsignedSmallInteger('versi_pertanyaan')->default(1);
            $table->json('jawaban');
            $table->text('saran')->nullable();
            $table->timestamp('diisi_pada');
            $table->timestamps();

            $table->unique(
                ['guru_mata_pelajaran_id', 'siswa_id', 'semester'],
                'survei_pembelajaran_guru_siswa_semester_unik',
            );
            $table->index(
                ['siswa_id', 'semester', 'diisi_pada'],
                'survei_pembelajaran_siswa_semester_tanggal',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survei_pembelajaran');
    }
};
