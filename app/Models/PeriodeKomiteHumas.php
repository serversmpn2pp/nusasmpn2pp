<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodeKomiteHumas extends Model
{
    protected $table = 'periode_komite_humas';

    public const STATUS = ['draf' => 'Draf', 'aktif' => 'Aktif', 'arsip' => 'Diarsipkan'];

    public const MASA = ['belum_mulai' => 'Belum mulai', 'berjalan' => 'Sedang berjalan', 'segera_berakhir' => 'Berakhir dalam 30 hari', 'berakhir' => 'Masa bakti berakhir'];

    public const MIME_SK = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    public const KOLOM = ['nama', 'tanggal_mulai', 'tanggal_selesai', 'nomor_sk', 'tanggal_sk', 'status', 'catatan'];

    protected $fillable = ['token_pembuatan', ...self::KOLOM];

    protected $hidden = ['token_pembuatan'];

    protected $casts = ['tanggal_mulai' => 'date', 'tanggal_selesai' => 'date', 'tanggal_sk' => 'date', 'versi' => 'integer'];

    public function pengurus(): HasMany
    {
        return $this->hasMany(PengurusKomiteHumas::class)->orderBy('id');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatKomiteHumas::class)->latest('id');
    }

    public function berkas(): BelongsTo
    {
        return $this->belongsTo(RiwayatDokumenHumas::class, 'riwayat_dokumen_humas_id');
    }

    public function masaBakti(): string
    {
        if ($this->tanggal_selesai->isBefore(today())) {
            return 'berakhir';
        }
        if ($this->tanggal_mulai->isAfter(today())) {
            return 'belum_mulai';
        }

        return $this->tanggal_selesai->lte(today()->addDays(30)) ? 'segera_berakhir' : 'berjalan';
    }

    public function snapshot(): array
    {
        return array_replace($this->only(self::KOLOM), ['tanggal_mulai' => $this->tanggal_mulai->format('d-m-Y'), 'tanggal_selesai' => $this->tanggal_selesai->format('d-m-Y'),
            'tanggal_sk' => $this->tanggal_sk?->format('d-m-Y'), 'pengurus' => $this->pengurus()->get(['id', 'nama', 'jabatan', 'aktif'])->toArray()]);
    }
}
