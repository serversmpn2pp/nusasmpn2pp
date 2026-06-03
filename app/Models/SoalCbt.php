<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SoalCbt extends Model
{
    protected $table = 'soal_cbt';

    public const DAFTAR_JENIS = [
        'pilihan_ganda' => 'Pilihan Ganda',
        'pilihan_ganda_kompleks' => 'Pilihan Ganda Kompleks',
        'benar_salah' => 'Benar-Salah',
        'menjodohkan' => 'Menjodohkan',
        'isian_singkat' => 'Isian Singkat',
        'uraian' => 'Uraian',
        'numerik' => 'Numerik',
        'upload_file' => 'Upload File',
    ];

    public const DAFTAR_KESULITAN = [
        'mudah' => 'Mudah',
        'sedang' => 'Sedang',
        'sulit' => 'Sulit',
    ];

    public const DAFTAR_KATEGORI = [
        'umum' => 'Umum',
        'stimulus' => 'Berbasis Stimulus',
        'hots' => 'HOTS',
        'literasi' => 'Literasi',
        'numerasi' => 'Numerasi',
        'praktik' => 'Praktik',
    ];

    public const DAFTAR_STATUS = [
        'draft' => 'Draft',
        'siap' => 'Siap digunakan',
        'arsip' => 'Arsip',
    ];

    protected $fillable = [
        'tahun_pelajaran_id',
        'mata_pelajaran_id',
        'tingkat',
        'kode',
        'jenis_soal',
        'tingkat_kesulitan',
        'kategori',
        'topik',
        'materi',
        'tujuan_pembelajaran',
        'stimulus',
        'pertanyaan',
        'opsi',
        'kunci_jawaban',
        'rubrik',
        'media',
        'skor_maksimal',
        'pembahasan',
        'status',
        'aktif',
        'dibuat_oleh_pengguna_id',
    ];

    protected $casts = [
        'tingkat' => 'integer',
        'opsi' => 'array',
        'kunci_jawaban' => 'array',
        'rubrik' => 'array',
        'media' => 'array',
        'skor_maksimal' => 'decimal:2',
        'aktif' => 'boolean',
    ];

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh_pengguna_id');
    }

    public function soalUjianCbt(): HasMany
    {
        return $this->hasMany(SoalUjianCbt::class);
    }

    public function labelJenis(): string
    {
        return self::DAFTAR_JENIS[$this->jenis_soal] ?? str($this->jenis_soal)->headline()->toString();
    }

    public function labelKesulitan(): string
    {
        return self::DAFTAR_KESULITAN[$this->tingkat_kesulitan] ?? str($this->tingkat_kesulitan)->headline()->toString();
    }

    public function labelKategori(): string
    {
        return self::DAFTAR_KATEGORI[$this->kategori] ?? str($this->kategori)->headline()->toString();
    }

    public function labelStatus(): string
    {
        return self::DAFTAR_STATUS[$this->status] ?? str($this->status)->headline()->toString();
    }
}
