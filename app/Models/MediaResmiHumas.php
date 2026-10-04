<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaResmiHumas extends Model
{
    protected $table = 'media_resmi_humas';

    public const JENIS = ['website' => 'Website', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'lainnya' => 'Media lainnya'];

    public const STATUS = ['aktif' => 'Aktif', 'nonaktif' => 'Tidak aktif', 'arsip' => 'Diarsipkan'];

    public const IZIN_LIHAT = ['media_resmi_humas.lihat', 'media_resmi_humas.kelola'];

    public const KOLOM = ['nama', 'jenis', 'tautan', 'identitas_akun', 'penanggung_jawab', 'jabatan_penanggung_jawab', 'status', 'tanggal_diperiksa', 'catatan'];

    protected $fillable = ['token_pembuatan', 'tautan_hash', ...self::KOLOM];

    protected $hidden = ['token_pembuatan', 'tautan_hash'];

    protected $casts = ['tanggal_diperiksa' => 'date', 'versi' => 'integer'];

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatMediaResmiHumas::class)->latest('id');
    }

    public function snapshot(): array
    {
        return array_replace($this->only(self::KOLOM), ['tanggal_diperiksa' => $this->tanggal_diperiksa?->format('d-m-Y')]);
    }
}
