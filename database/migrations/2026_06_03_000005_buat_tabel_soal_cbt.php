<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soal_cbt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_pelajaran_id')
                ->nullable()
                ->constrained('tahun_pelajaran')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('mata_pelajaran_id')
                ->constrained('mata_pelajaran')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedTinyInteger('tingkat');
            $table->string('kode', 60)->unique();
            $table->string('jenis_soal', 40)->index();
            $table->string('tingkat_kesulitan', 30)->default('sedang')->index();
            $table->string('kategori', 40)->default('umum')->index();
            $table->string('topik', 160)->nullable();
            $table->string('materi', 180)->nullable();
            $table->text('tujuan_pembelajaran')->nullable();
            $table->text('stimulus')->nullable();
            $table->text('pertanyaan');
            $table->json('opsi')->nullable();
            $table->json('kunci_jawaban')->nullable();
            $table->json('rubrik')->nullable();
            $table->json('media')->nullable();
            $table->decimal('skor_maksimal', 6, 2)->default(1);
            $table->text('pembahasan')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->boolean('aktif')->default(true)->index();
            $table->foreignId('dibuat_oleh_pengguna_id')
                ->nullable()
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();

            $table->index(['mata_pelajaran_id', 'tingkat']);
            $table->index(['tahun_pelajaran_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soal_cbt');
    }
};
