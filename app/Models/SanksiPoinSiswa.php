<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SanksiPoinSiswa extends Model
{
    protected $table = 'sanksi_poin_siswa';

    protected $fillable = [
        'siswa_id',
        'tahun_pelajaran_id',
        'aturan_sanksi_poin_id',
        'poin_saat_terpicu',
        'status',
        'terpicu_pada',
        'dilaksanakan_pada',
        'petugas_pegawai_id',
        'catatan',
    ];

    protected $casts = [
        'poin_saat_terpicu' => 'integer',
        'terpicu_pada' => 'datetime',
        'dilaksanakan_pada' => 'datetime',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function aturanSanksiPoin(): BelongsTo
    {
        return $this->belongsTo(AturanSanksiPoin::class);
    }

    public function petugasPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'petugas_pegawai_id');
    }
}
