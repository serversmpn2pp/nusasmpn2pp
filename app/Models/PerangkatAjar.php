<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerangkatAjar extends Model
{
    protected $table = 'perangkat_ajar';

    public const STATUS = [
        'menunggu_pemeriksaan' => 'Menunggu pemeriksaan',
        'perlu_perbaikan' => 'Perlu perbaikan',
        'sudah_diperiksa' => 'Sudah diperiksa',
    ];

    protected $fillable = [
        'pegawai_id',
        'tahun_pelajaran_id',
        'semester',
        'mata_pelajaran_id',
        'jenis_perangkat_ajar_id',
        'judul',
        'catatan_guru',
        'lokasi_file',
        'nama_file_asli',
        'tipe_file',
        'ukuran_file',
        'status',
        'pemeriksa_pegawai_id',
        'catatan_pemeriksa',
        'diunggah_pada',
        'diperiksa_pada',
    ];

    protected $casts = [
        'semester' => 'integer',
        'ukuran_file' => 'integer',
        'diunggah_pada' => 'datetime',
        'diperiksa_pada' => 'datetime',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function jenisPerangkatAjar(): BelongsTo
    {
        return $this->belongsTo(JenisPerangkatAjar::class);
    }

    public function pemeriksa(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pemeriksa_pegawai_id');
    }

    public function riwayatFile(): HasMany
    {
        return $this->hasMany(RiwayatFilePerangkatAjar::class)
            ->orderByDesc('diunggah_pada');
    }

    public function labelStatus(): string
    {
        return self::STATUS[$this->status] ?? str($this->status)->headline()->toString();
    }

    public function kelasBadgeStatus(): string
    {
        return match ($this->status) {
            'sudah_diperiksa' => 'badge-active',
            'perlu_perbaikan' => 'badge-danger',
            default => 'badge-inactive',
        };
    }

    public function ukuranFileTampil(): string
    {
        return number_format($this->ukuran_file / 1024 / 1024, 2, ',', '.') . ' MB';
    }
}
