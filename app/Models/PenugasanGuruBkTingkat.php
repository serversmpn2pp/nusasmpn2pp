<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenugasanGuruBkTingkat extends Model
{
    protected $table = 'penugasan_guru_bk_tingkat';

    protected $fillable = [
        'tahun_pelajaran_id',
        'pegawai_id',
        'tingkat',
        'tanggal_mulai',
        'tanggal_selesai',
        'aktif',
        'dibuat_oleh_pengguna_id',
    ];

    protected $casts = [
        'tingkat' => 'integer',
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'aktif' => 'boolean',
    ];

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function dibuatOlehPengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh_pengguna_id');
    }
}
