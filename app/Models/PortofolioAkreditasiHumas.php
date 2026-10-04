<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PortofolioAkreditasiHumas extends Model
{
    public const STATUS = ['draf' => 'Draf', 'siap' => 'Siap', 'arsip' => 'Arsip'];

    public const KOLOM = ['tahun_pelajaran_id', 'nama', 'instrumen', 'penanggung_jawab', 'catatan'];

    protected $table = 'portofolio_akreditasi_humas';

    protected $fillable = ['token_pembuatan', ...self::KOLOM];

    protected $hidden = ['token_pembuatan'];

    protected $casts = ['versi' => 'integer'];

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function butir(): HasMany
    {
        return $this->hasMany(ButirAkreditasiHumas::class)->whereNull('dihapus_pada')->orderBy('urutan')->orderBy('id');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatAkreditasiHumas::class)->orderByDesc('id');
    }
}
