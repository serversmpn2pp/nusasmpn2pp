<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengaturanBatasProsesPelanggaran extends Model
{
    protected $table = 'pengaturan_batas_proses_pelanggaran';

    protected $fillable = [
        'tahun_pelajaran_id', 'batas_hari_pemeriksaan_bk', 'batas_hari_persetujuan',
        'batas_hari_musyawarah', 'pengingat_hari_sebelum_batas',
        'notifikasi_pengingat_aktif', 'notifikasi_terlambat_aktif', 'diperbarui_oleh_pengguna_id',
    ];

    protected $casts = [
        'batas_hari_pemeriksaan_bk' => 'integer',
        'batas_hari_persetujuan' => 'integer',
        'batas_hari_musyawarah' => 'integer',
        'pengingat_hari_sebelum_batas' => 'integer',
        'notifikasi_pengingat_aktif' => 'boolean',
        'notifikasi_terlambat_aktif' => 'boolean',
    ];

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function diperbaruiOlehPengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diperbarui_oleh_pengguna_id');
    }
}
