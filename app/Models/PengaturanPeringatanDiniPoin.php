<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengaturanPeringatanDiniPoin extends Model
{
    protected $table = 'pengaturan_peringatan_dini_poin';

    protected $fillable = [
        'tahun_pelajaran_id',
        'aktif',
        'persentase_mendekati_ambang',
        'jumlah_pelanggaran_berulang',
        'periode_pelanggaran_hari',
        'jumlah_keterlambatan_berulang',
        'periode_keterlambatan_hari',
        'notifikasi_aktif',
        'diperbarui_oleh_pengguna_id',
    ];

    protected $casts = [
        'aktif' => 'boolean',
        'persentase_mendekati_ambang' => 'integer',
        'jumlah_pelanggaran_berulang' => 'integer',
        'periode_pelanggaran_hari' => 'integer',
        'jumlah_keterlambatan_berulang' => 'integer',
        'periode_keterlambatan_hari' => 'integer',
        'notifikasi_aktif' => 'boolean',
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
