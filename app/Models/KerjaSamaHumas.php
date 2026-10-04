<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KerjaSamaHumas extends Model
{
    protected $table = 'kerja_sama_humas';

    protected $hidden = ['token_unggahan_mou'];

    public const BIDANG = ['pendidikan' => 'Pendidikan', 'kesehatan' => 'Kesehatan', 'lingkungan' => 'Lingkungan', 'seni_budaya' => 'Seni & budaya',
        'teknologi' => 'Teknologi', 'keamanan' => 'Keamanan', 'kewirausahaan' => 'Kewirausahaan', 'sosial' => 'Sosial', 'lainnya' => 'Lainnya'];

    public const STATUS = ['draf' => 'Draf', 'aktif' => 'Aktif', 'diakhiri' => 'Diakhiri'];

    public const STATUS_BERLAKU = ['draf' => 'Draf', 'belum_mulai' => 'Belum mulai', 'berlaku' => 'Berlaku', 'segera_berakhir' => 'Segera berakhir', 'kedaluwarsa' => 'Kedaluwarsa', 'diakhiri' => 'Diakhiri'];

    public const PENGINGAT = [0, 7, 14, 30, 60, 90];

    protected $fillable = ['judul', 'nomor', 'bidang', 'ruang_lingkup', 'penanggung_jawab', 'tanggal_mulai', 'tanggal_selesai',
        'ingatkan_hari_sebelum', 'status', 'alasan_diakhiri', 'dokumen_humas_id', 'versi', 'diubah_oleh_pengguna_id'];

    protected $casts = ['tanggal_mulai' => 'date', 'tanggal_selesai' => 'date', 'ingatkan_hari_sebelum' => 'integer', 'versi' => 'integer'];

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(MitraHumas::class, 'mitra_humas_id');
    }

    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(DokumenHumas::class, 'dokumen_humas_id');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatKemitraanHumas::class)->latest('id');
    }

    public function statusBerlaku(): string
    {
        if ($this->status !== 'aktif') {
            return $this->status;
        }
        if (! $this->tanggal_mulai || ! $this->tanggal_selesai) {
            return 'draf';
        }
        if ($this->tanggal_selesai->lt(today())) {
            return 'kedaluwarsa';
        }
        if ($this->tanggal_mulai->gt(today())) {
            return 'belum_mulai';
        }

        return $this->tanggal_selesai->lte(today()->addDays($this->ingatkan_hari_sebelum)) ? 'segera_berakhir' : 'berlaku';
    }

    public function scopeDenganStatusBerlaku(Builder $query, string $status): void
    {
        if (in_array($status, ['draf', 'diakhiri'], true)) {
            $query->where('status', $status);

            return;
        }
        $query->where('status', 'aktif')->whereNotNull('tanggal_mulai')->whereNotNull('tanggal_selesai');
        if ($status === 'kedaluwarsa') {
            $query->whereDate('tanggal_selesai', '<', today());
        } elseif ($status === 'belum_mulai') {
            $query->whereDate('tanggal_mulai', '>', today());
        } else {
            $query->whereDate('tanggal_mulai', '<=', today())->whereDate('tanggal_selesai', '>=', today())
                ->where(function ($q) use ($status) {
                    foreach (self::PENGINGAT as $hari) {
                        $q->orWhere(fn ($q) => $q->where('ingatkan_hari_sebelum', $hari)
                            ->whereDate('tanggal_selesai', $status === 'segera_berakhir' ? '<=' : '>', today()->addDays($hari)));
                    }
                });
        }
    }
}
