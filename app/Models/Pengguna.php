<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use RuntimeException;

class Pengguna extends Authenticatable
{
    use Notifiable;

    protected $table = 'pengguna';

    protected $authPasswordName = 'kata_sandi';

    protected $fillable = [
        'pegawai_id',
        'siswa_id',
        'nama',
        'username',
        'kata_sandi',
        'kata_sandi_awal',
        'wajib_ganti_kata_sandi',
        'peran',
        'aktif',
        'akun_sistem',
        'terakhir_login_pada',
    ];

    protected $hidden = [
        'kata_sandi',
        'kata_sandi_awal',
        'remember_token',
    ];

    protected $casts = [
        'kata_sandi' => 'hashed',
        'kata_sandi_awal' => 'encrypted',
        'wajib_ganti_kata_sandi' => 'boolean',
        'aktif' => 'boolean',
        'akun_sistem' => 'boolean',
        'terakhir_login_pada' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Pengguna $pengguna) {
            if ($pengguna->akun_sistem) {
                throw new RuntimeException('Akun sistem tidak dapat dihapus.');
            }
        });
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function daftarPeran(): BelongsToMany
    {
        return $this->belongsToMany(Peran::class, 'pengguna_peran')
            ->withTimestamps();
    }

    public function laporanPembinaanSiswaDibuat(): HasMany
    {
        return $this->hasMany(LaporanPembinaanSiswa::class, 'dibuat_oleh_pengguna_id');
    }

    public function tindakLanjutPembinaanSiswaDibuat(): HasMany
    {
        return $this->hasMany(TindakLanjutPembinaanSiswa::class, 'dibuat_oleh_pengguna_id');
    }

    public function pendampinganSiswaDibuat(): HasMany
    {
        return $this->hasMany(PendampinganSiswa::class, 'dibuat_oleh_pengguna_id');
    }

    public function notifikasiPengguna(): HasMany
    {
        return $this->hasMany(NotifikasiPengguna::class);
    }

    public function penugasanGuruWaliSiswaDibuat(): HasMany
    {
        return $this->hasMany(PenugasanGuruWaliSiswa::class, 'dibuat_oleh_pengguna_id');
    }

    public function mutasiStokBarangDibuat(): HasMany
    {
        return $this->hasMany(MutasiStokBarang::class, 'dibuat_oleh_pengguna_id');
    }

    public function peminjamanBarangDibuat(): HasMany
    {
        return $this->hasMany(PeminjamanBarang::class, 'dibuat_oleh_pengguna_id');
    }

    public function pengembalianBarangDibuat(): HasMany
    {
        return $this->hasMany(PengembalianBarang::class, 'dibuat_oleh_pengguna_id');
    }

    public function ujianOmrDibuat(): HasMany
    {
        return $this->hasMany(UjianOmr::class, 'dibuat_oleh_pengguna_id');
    }

    public function ujianCbtDibuat(): HasMany
    {
        return $this->hasMany(UjianCbt::class, 'dibuat_oleh_pengguna_id');
    }

    public function soalCbtDibuat(): HasMany
    {
        return $this->hasMany(SoalCbt::class, 'dibuat_oleh_pengguna_id');
    }

    public function lembarJawabUjianOmrDibuat(): HasMany
    {
        return $this->hasMany(LembarJawabUjianOmr::class, 'dibuat_oleh_pengguna_id');
    }

    public function batchScanUjianOmrDibuat(): HasMany
    {
        return $this->hasMany(BatchScanUjianOmr::class, 'dibuat_oleh_pengguna_id');
    }

    public function memilikiPeran(string|array $kode): bool
    {
        $kode = (array) $kode;

        if ($this->relationLoaded('daftarPeran')) {
            return $this->daftarPeran
                ->whereIn('kode', $kode)
                ->where('aktif', true)
                ->isNotEmpty();
        }

        return $this->daftarPeran()
            ->whereIn('kode', $kode)
            ->where('aktif', true)
            ->exists();
    }

    public function memilikiIzin(string|array $kode): bool
    {
        $kode = (array) $kode;

        if ($this->administrator()) {
            return true;
        }

        if ($this->relationLoaded('daftarPeran')) {
            return $this->daftarPeran
                ->where('aktif', true)
                ->contains(fn (Peran $peran) => $peran->memilikiIzin($kode));
        }

        return $this->daftarPeran()
            ->where('peran.aktif', true)
            ->whereHas('izin', function ($query) use ($kode) {
                $query->whereIn('izin.kode', $kode)
                    ->where('izin.aktif', true);
            })
            ->exists();
    }

    public function administrator(): bool
    {
        return $this->peran === 'administrator'
            || $this->akun_sistem
            || $this->memilikiPeran('administrator');
    }

    public function akunPegawai(): bool
    {
        return ! $this->akun_sistem && filled($this->pegawai_id);
    }

    public function akunSiswa(): bool
    {
        return ! $this->akun_sistem && filled($this->siswa_id);
    }

    public function membatasiCakupanWaliKelas(): bool
    {
        if ($this->administrator()) {
            return false;
        }

        if (! $this->memilikiPeran('wali_kelas')) {
            return false;
        }

        return ! $this->memilikiPeran([
            'pimpinan',
            'wakil_pimpinan_kesiswaan',
            'wakil_pimpinan_kurikulum',
            'bk',
        ]);
    }

    public function kelasWaliIds(): array
    {
        if (! $this->pegawai_id) {
            return [];
        }

        return Kelas::query()
            ->where('wali_kelas_id', $this->pegawai_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function siswaWaliIds(): array
    {
        if (! $this->pegawai_id) {
            return [];
        }

        return PenugasanGuruWaliSiswa::query()
            ->where('guru_wali_pegawai_id', $this->pegawai_id)
            ->where('aktif', true)
            ->pluck('siswa_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function menjadiGuruWali(): bool
    {
        return $this->pegawai_id
            && PenugasanGuruWaliSiswa::query()
                ->where('guru_wali_pegawai_id', $this->pegawai_id)
                ->where('aktif', true)
                ->exists();
    }

    public function dapatMengaksesKelasSebagaiWali(?int $kelasId): bool
    {
        if (! $this->membatasiCakupanWaliKelas()) {
            return true;
        }

        if (! $kelasId) {
            return false;
        }

        return in_array($kelasId, $this->kelasWaliIds(), true);
    }

    public function melihatAbsensiPegawaiSemua(): bool
    {
        return $this->administrator()
            || $this->memilikiPeran([
                'pimpinan',
                'wakil_pimpinan_kesiswaan',
                'wakil_pimpinan_sarana_prasarana',
                'wakil_pimpinan_kurikulum',
            ]);
    }

    public function membatasiCakupanAbsensiPegawai(): bool
    {
        return ! $this->melihatAbsensiPegawaiSemua();
    }

    public function dapatMengaksesAbsensiPegawai(?int $pegawaiId): bool
    {
        if ($this->melihatAbsensiPegawaiSemua()) {
            return true;
        }

        if (! $pegawaiId || ! $this->pegawai_id) {
            return false;
        }

        return (int) $this->pegawai_id === (int) $pegawaiId;
    }
}
