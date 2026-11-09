<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class JenisPelanggaranSiswa extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $jenis): void {
            $nonaktif = $jenis->isDirty('aktif') && ! $jenis->aktif;
            $kategoriNonaktif = $jenis->isDirty('kategori_pembinaan_siswa_id') && $jenis->kategori_pembinaan_siswa_id
                && KategoriPembinaanSiswa::whereKey($jenis->kategori_pembinaan_siswa_id)->where('aktif', false)->exists();
            if (($nonaktif || $kategoriNonaktif) && $jenis->dipakaiPresensiManualTahunAktif()) {
                throw ValidationException::withMessages([$nonaktif ? 'aktif' : 'kategori_pembinaan_siswa_id' => 'Ganti aturan presensi manual pada tahun aktif sebelum menonaktifkan pelanggaran atau kategorinya.']);
            }
        });
    }

    public function dipakaiPresensiManualTahunAktif(): bool
    {
        return TahunPelajaran::where('aktif', true)->where(function ($query) {
            $query->whereHas('pengaturanPoinKeterlambatan', fn ($q) => $q->where('pelanggaran_hadir_manual_id', $this->id));
            if ($this->getOriginal('kode') === 'PRESENSI_MANUAL') {
                $query->orWhereDoesntHave('pengaturanPoinKeterlambatan', fn ($q) => $q->whereNotNull('pelanggaran_hadir_manual_id'));
            }
        })->exists();
    }

    protected $table = 'jenis_pelanggaran_siswa';

    protected $fillable = [
        'kategori_pembinaan_siswa_id',
        'kode',
        'nama',
        'tingkat',
        'poin',
        'urutan',
        'aktif',
    ];

    protected $casts = [
        'kategori_pembinaan_siswa_id' => 'integer',
        'poin' => 'integer',
        'urutan' => 'integer',
        'aktif' => 'boolean',
    ];

    public function kategoriPembinaanSiswa(): BelongsTo
    {
        return $this->belongsTo(KategoriPembinaanSiswa::class);
    }

    public function butirPelanggaranLaporan(): HasMany
    {
        return $this->hasMany(ButirPelanggaranLaporan::class);
    }
}
