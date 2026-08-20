<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PenugasanPendampingIbadahSiswi extends Model
{
    protected $table = 'penugasan_pendamping_ibadah_siswi';

    protected $fillable = [
        'tahun_pelajaran_id',
        'pegawai_id',
        'semua_kelas',
        'aktif',
        'ditugaskan_oleh_pengguna_id',
        'dinonaktifkan_pada',
    ];

    protected $casts = [
        'semua_kelas' => 'boolean',
        'aktif' => 'boolean',
        'dinonaktifkan_pada' => 'datetime',
    ];

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function kelas(): BelongsToMany
    {
        return $this->belongsToMany(
            Kelas::class,
            'kelas_pendamping_ibadah_siswi',
            'penugasan_pendamping_ibadah_siswi_id',
            'kelas_id'
        )->withTimestamps();
    }

    public function ditugaskanOlehPengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'ditugaskan_oleh_pengguna_id');
    }
}
