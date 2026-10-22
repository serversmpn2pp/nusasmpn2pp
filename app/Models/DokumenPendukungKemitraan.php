<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DokumenPendukungKemitraan extends Model
{
    protected $table = 'dokumen_pendukung_kemitraan';

    public const JENIS = ['proposal' => 'Proposal', 'korespondensi' => 'Korespondensi', 'laporan' => 'Laporan pelaksanaan',
        'berita_acara' => 'Berita acara', 'dokumentasi' => 'Dokumentasi', 'lainnya' => 'Pendukung lainnya'];

    protected $guarded = ['id'];

    protected $casts = ['dilepas_pada' => 'datetime'];

    public function berkas(): BelongsTo
    {
        return $this->belongsTo(RiwayatDokumenHumas::class, 'riwayat_dokumen_humas_id');
    }

    public function mou(): BelongsTo
    {
        return $this->belongsTo(KerjaSamaHumas::class, 'kerja_sama_humas_id');
    }
}
