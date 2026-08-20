<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengaturanBerhalanganIbadah extends Model
{
    protected $table = 'pengaturan_berhalangan_ibadah';

    protected $fillable = [
        'tahun_pelajaran_id',
        'batas_hari_konfirmasi',
        'aktif',
        'diperbarui_oleh_pengguna_id',
    ];

    protected $casts = [
        'batas_hari_konfirmasi' => 'integer',
        'aktif' => 'boolean',
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
