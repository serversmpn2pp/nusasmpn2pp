<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatPergantianGuruMapel extends Model
{
    protected $table = 'riwayat_pergantian_guru_mapel';

    protected $fillable = [
        'guru_mata_pelajaran_id',
        'tahun_pelajaran_id',
        'kelas_id',
        'mata_pelajaran_id',
        'pegawai_lama_id',
        'pegawai_baru_id',
        'tanggal_efektif',
        'alasan',
        'diganti_oleh_pengguna_id',
    ];

    protected $casts = [
        'tanggal_efektif' => 'date',
    ];

    public function guruMataPelajaran(): BelongsTo
    {
        return $this->belongsTo(GuruMataPelajaran::class);
    }

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function pegawaiLama(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_lama_id');
    }

    public function pegawaiBaru(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_baru_id');
    }

    public function digantiOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diganti_oleh_pengguna_id');
    }
}
