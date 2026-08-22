<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MataPelajaran extends Model
{
    public const PREDIKAT_NILAI = ['SB', 'B', 'C', 'K'];

    public const KELOMPOK_PENILAIAN_PREDIKAT = [
        'Pengembangan Diri',
        'Kokurikuler',
        'Ekstrakurikuler',
    ];

    protected $table = 'mata_pelajaran';

    protected $fillable = [
        'kode',
        'nama',
        'kelompok',
        'tingkat',
        'kkm',
        'urutan',
        'aktif',
        'keterangan',
    ];

    protected $casts = [
        'tingkat' => 'integer',
        'kkm' => 'integer',
        'urutan' => 'integer',
        'aktif' => 'boolean',
    ];

    public function guruMataPelajaran(): HasMany
    {
        return $this->hasMany(GuruMataPelajaran::class);
    }

    public function pengaturanTingkat(): HasMany
    {
        return $this->hasMany(PengaturanMataPelajaran::class);
    }

    public function tersediaUntuk(int $tahunPelajaranId, int $tingkat): bool
    {
        $memilikiPengaturan = $this->pengaturanTingkat()->exists();

        if ($memilikiPengaturan) {
            return $this->pengaturanTingkat()
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->where('tingkat', $tingkat)
                ->where('aktif', true)
                ->exists();
        }

        return ! $this->tingkat || (int) $this->tingkat === $tingkat;
    }

    public function pengaturanUntuk(int $tahunPelajaranId, int $tingkat): ?PengaturanMataPelajaran
    {
        return $this->pengaturanTingkat
            ->first(fn (PengaturanMataPelajaran $pengaturan) => (
                (int) $pengaturan->tahun_pelajaran_id === $tahunPelajaranId
                && $pengaturan->tingkat === $tingkat
            ));
    }

    public static function kelompokMenggunakanPredikat(?string $kelompok): bool
    {
        return collect(self::KELOMPOK_PENILAIAN_PREDIKAT)
            ->contains(fn (string $item) => mb_strtolower($item) === mb_strtolower(trim((string) $kelompok)));
    }

    public function menggunakanPredikat(): bool
    {
        return self::kelompokMenggunakanPredikat($this->kelompok);
    }

    public function labelJenisPenilaian(): string
    {
        return $this->menggunakanPredikat()
            ? 'Predikat (SB/B/C/K)'
            : 'Angka (0-100)';
    }

    public function perangkatAjar(): HasMany
    {
        return $this->hasMany(PerangkatAjar::class);
    }

    public function ujianCbt(): HasMany
    {
        return $this->hasMany(UjianCbt::class);
    }

    public function soalCbt(): HasMany
    {
        return $this->hasMany(SoalCbt::class);
    }
}
