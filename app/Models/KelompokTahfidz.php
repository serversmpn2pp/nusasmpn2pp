<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KelompokTahfidz extends Model
{
    protected $table = 'kelompok_tahfidz';

    protected $fillable = ['tahun_pelajaran_id', 'pembimbing_pegawai_id', 'nama', 'aktif'];

    protected $casts = ['aktif' => 'boolean', 'versi' => 'integer'];

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function pembimbing(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pembimbing_pegawai_id');
    }

    public function anggota(): HasMany
    {
        return $this->hasMany(AnggotaTahfidz::class);
    }

    public function anggotaAktif(): HasMany
    {
        return $this->anggota()->where('aktif', true);
    }

    public function pertemuan(): HasMany
    {
        return $this->hasMany(PertemuanTahfidz::class);
    }
}
