<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MitraHumas extends Model
{
    protected $table = 'mitra_humas';

    public const JENIS = ['pemerintah' => 'Instansi pemerintah', 'pendidikan' => 'Lembaga pendidikan', 'kesehatan' => 'Layanan kesehatan',
        'usaha' => 'Dunia usaha', 'komunitas' => 'Komunitas / organisasi', 'lainnya' => 'Lainnya'];

    public const STATUS = ['aktif' => 'Aktif', 'arsip' => 'Diarsipkan'];

    protected $fillable = ['nama', 'jenis', 'alamat', 'nama_kontak', 'jabatan_kontak', 'nomor_kontak', 'email', 'website', 'catatan', 'status', 'versi', 'diubah_oleh_pengguna_id'];

    protected $casts = ['versi' => 'integer'];

    public function kerjaSama(): HasMany
    {
        return $this->hasMany(KerjaSamaHumas::class)->orderByDesc('tanggal_mulai')->orderByDesc('id');
    }

    public function agenda(): BelongsToMany
    {
        return $this->belongsToMany(AgendaHumas::class, 'mitra_humas_agenda')->withTimestamps();
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatKemitraanHumas::class)->latest('id');
    }
}
