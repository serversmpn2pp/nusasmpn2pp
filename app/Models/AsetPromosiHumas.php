<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AsetPromosiHumas extends Model
{
    protected $table = 'aset_promosi_humas';

    public const KATEGORI = ['logo' => 'Logo resmi', 'profil' => 'Profil sekolah', 'brosur' => 'Brosur', 'foto' => 'Foto kegiatan', 'template' => 'Template desain', 'video' => 'Video / audio', 'lainnya' => 'Materi lainnya'];

    public const STATUS = ['aktif' => 'Aktif', 'arsip' => 'Diarsipkan'];

    public const IZIN_LIHAT = ['aset_promosi_humas.lihat', 'aset_promosi_humas.kelola'];

    protected $fillable = ['token_pembuatan', 'nama', 'kategori', 'deskripsi', 'tanggal_aset', 'kata_kunci', 'kredit', 'ketentuan_penggunaan', 'dibuat_oleh_pengguna_id'];

    protected $hidden = ['token_pembuatan'];

    protected $casts = ['tanggal_aset' => 'date', 'versi' => 'integer'];

    public function berkas(): BelongsTo
    {
        return $this->belongsTo(RiwayatDokumenHumas::class, 'riwayat_dokumen_humas_id');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatAsetPromosiHumas::class)->latest('id');
    }

    public function pemakaian(): HasMany
    {
        return $this->hasMany(AsetPublikasiHumas::class, 'aset_promosi_humas_id')->latest('id');
    }

    public function snapshot(): array
    {
        return ['nama' => $this->nama, 'kategori' => $this->kategori, 'deskripsi' => $this->deskripsi,
            'tanggal_aset' => $this->tanggal_aset->format('d-m-Y'), 'kata_kunci' => $this->kata_kunci,
            'kredit' => $this->kredit, 'ketentuan_penggunaan' => $this->ketentuan_penggunaan,
            'sumber' => $this->sumber, 'tautan' => $this->tautan, 'status' => $this->status, 'versi' => $this->versi];
    }
}
