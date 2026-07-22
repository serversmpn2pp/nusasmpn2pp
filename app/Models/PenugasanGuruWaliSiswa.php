<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenugasanGuruWaliSiswa extends Model
{
    protected $table = 'penugasan_guru_wali_siswa';

    protected $fillable = [
        'siswa_id',
        'guru_wali_pegawai_id',
        'tanggal_mulai',
        'tanggal_selesai',
        'nomor_sk',
        'catatan',
        'aktif',
        'dibuat_oleh_pengguna_id',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'aktif' => 'boolean',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function guruWali(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'guru_wali_pegawai_id');
    }

    public function dibuatOlehPengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh_pengguna_id');
    }
}
