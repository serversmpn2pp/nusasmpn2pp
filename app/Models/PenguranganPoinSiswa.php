<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PenguranganPoinSiswa extends Model
{
    protected $table = 'pengurangan_poin_siswa';

    public const DAFTAR_STATUS = [
        'diajukan' => 'Diajukan',
        'disetujui' => 'Disetujui',
        'ditolak' => 'Ditolak',
    ];

    protected $fillable = [
        'siswa_id',
        'tahun_pelajaran_id',
        'tanggal_kegiatan',
        'jenis_kegiatan',
        'deskripsi',
        'poin_pengurangan',
        'bukti',
        'status',
        'diajukan_oleh_pengguna_id',
        'disetujui_oleh_pegawai_id',
        'diputuskan_pada',
        'catatan_keputusan',
    ];

    protected $casts = [
        'tanggal_kegiatan' => 'date',
        'poin_pengurangan' => 'integer',
        'diputuskan_pada' => 'datetime',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function diajukanOlehPengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diajukan_oleh_pengguna_id');
    }

    public function disetujuiOlehPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'disetujui_oleh_pegawai_id');
    }

    public function transaksiPoinSiswa(): HasMany
    {
        return $this->hasMany(TransaksiPoinSiswa::class);
    }
}
