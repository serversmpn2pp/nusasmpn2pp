<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatKoreksiKegiatanIbadah extends Model
{
    protected $table = 'riwayat_koreksi_kegiatan_ibadah';

    protected $fillable = [
        'presensi_kegiatan_ibadah_id',
        'kegiatan_ibadah_id',
        'tahun_pelajaran_id',
        'kelas_id',
        'anggota_kelas_id',
        'siswa_id',
        'diubah_oleh_pengguna_id',
        'tanggal',
        'tindakan',
        'hadir_sebelum',
        'hadir_sesudah',
        'waktu_sebelum',
        'waktu_sesudah',
        'sumber_sebelum',
        'sumber_sesudah',
        'alasan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'hadir_sebelum' => 'boolean',
        'hadir_sesudah' => 'boolean',
    ];

    public function presensiKegiatanIbadah(): BelongsTo
    {
        return $this->belongsTo(PresensiKegiatanIbadah::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function diubahOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diubah_oleh_pengguna_id');
    }
}
