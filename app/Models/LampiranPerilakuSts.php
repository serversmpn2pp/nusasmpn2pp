<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LampiranPerilakuSts extends Model
{
    protected $table = 'lampiran_perilaku_sts';

    protected $fillable = ['rapor_sts_kelas_id', 'anggota_kelas_id', 'guru_bk_id', 'wakil_kesiswaan_id',
        'baris', 'ringkasan', 'catatan', 'sidik_sumber', 'versi', 'diperiksa_pada', 'diperiksa_oleh_pengguna_id'];

    protected $casts = ['baris' => 'array', 'ringkasan' => 'array', 'versi' => 'integer', 'diperiksa_pada' => 'datetime'];

    public function guruBk(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'guru_bk_id');
    }

    public function wakilKesiswaan(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'wakil_kesiswaan_id');
    }

    public function pemeriksa(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diperiksa_oleh_pengguna_id');
    }
}
