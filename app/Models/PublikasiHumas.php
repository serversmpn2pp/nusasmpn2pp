<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublikasiHumas extends Model
{
    protected $table = 'publikasi_humas';

    public const STATUS = ['draf' => 'Draf', 'diajukan' => 'Menunggu pemeriksaan', 'revisi' => 'Perlu revisi', 'disetujui' => 'Disetujui', 'tayang' => 'Sudah tayang'];

    public const JENIS = ['berita' => 'Berita kegiatan', 'pengumuman' => 'Pengumuman', 'rilis_pers' => 'Rilis pers'];

    public const KANAL = ['website' => 'Website sekolah', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'youtube' => 'YouTube', 'media_massa' => 'Media massa', 'lainnya' => 'Media lainnya'];

    public const IZIN_LIHAT = ['publikasi_humas.lihat', 'publikasi_humas.kelola', 'publikasi_humas.periksa'];

    protected $fillable = ['token_pembuatan', 'judul', 'jenis', 'ringkasan', 'isi', 'kanal', 'rencana_tayang', 'agenda_humas_id', 'dibuat_oleh_pengguna_id'];

    protected $hidden = ['token_pembuatan'];

    protected $casts = ['rencana_tayang' => 'date', 'diajukan_pada' => 'datetime', 'diperiksa_pada' => 'datetime', 'waktu_tayang' => 'datetime', 'versi' => 'integer'];

    public function bolehDiedit(): bool
    {
        return in_array($this->status, ['draf', 'revisi'], true);
    }

    public function agenda(): BelongsTo
    {
        return $this->belongsTo(AgendaHumas::class, 'agenda_humas_id');
    }

    public function lampiran(): HasMany
    {
        return $this->hasMany(LampiranPublikasiHumas::class)->orderBy('id');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatPublikasiHumas::class)->latest('id');
    }

    public function aset(): HasMany
    {
        return $this->hasMany(AsetPublikasiHumas::class)->orderBy('id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh_pengguna_id');
    }

    public function pemeriksa(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diperiksa_oleh_pengguna_id');
    }
}
