<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SuratTu extends Model
{
    protected $table = 'surat_tu';

    public const JENIS = ['masuk' => 'Surat masuk', 'keluar' => 'Surat keluar', 'tugas' => 'Surat tugas'];

    public const SIFAT = ['biasa' => 'Biasa', 'penting' => 'Penting', 'rahasia' => 'Rahasia'];

    public const STATUS = ['tercatat' => 'Tercatat', 'draf' => 'Draf', 'diajukan' => 'Menunggu pengesahan',
        'dikembalikan' => 'Perlu perbaikan', 'disahkan' => 'Disahkan', 'dikirim' => 'Dikirim', 'dibatalkan' => 'Dibatalkan'];

    protected $guarded = ['id'];

    protected $hidden = ['nomor_unik', 'agenda_unik'];

    protected $casts = ['tanggal_surat' => 'date', 'tanggal_diterima' => 'date', 'mulai_tugas' => 'date',
        'selesai_tugas' => 'date', 'diajukan_pada' => 'datetime', 'disahkan_pada' => 'datetime',
        'dikirim_pada' => 'datetime', 'dibatalkan_pada' => 'datetime', 'versi' => 'integer'];

    public function berkas(): HasMany
    {
        return $this->hasMany(BerkasSuratTu::class, 'surat_tu_id')->orderBy('id');
    }

    public function penugasan(): HasMany
    {
        return $this->hasMany(PenugasanSuratTu::class, 'surat_tu_id')->orderBy('nama_pegawai');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatSuratTu::class, 'surat_tu_id')->latest('id');
    }

    public function tindakLanjut(): HasMany
    {
        return $this->hasMany(TindakLanjutSuratTu::class, 'surat_tu_id');
    }

    public function pengesah(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pengesah_pengguna_id');
    }

    public function revisiDari(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revisi_dari_id');
    }

    public function revisi(): HasMany
    {
        return $this->hasMany(self::class, 'revisi_dari_id');
    }

    public function dapatDiedit(): bool
    {
        return in_array($this->status, ['tercatat', 'draf', 'dikembalikan'], true)
            && ($this->jenis !== 'masuk' || ! $this->tindakLanjut()->exists());
    }
}
