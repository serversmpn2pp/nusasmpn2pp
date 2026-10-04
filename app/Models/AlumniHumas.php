<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlumniHumas extends Model
{
    protected $table = 'alumni_humas';

    public const STATUS = ['aktif' => 'Aktif', 'arsip' => 'Diarsipkan'];

    public const PENELUSURAN = ['belum_terdata' => 'Belum terdata', 'melanjutkan' => 'Melanjutkan pendidikan', 'tidak_melanjutkan' => 'Tidak melanjutkan'];

    public const SEKOLAH = ['sma' => 'SMA', 'smk' => 'SMK', 'ma' => 'MA', 'pesantren' => 'Pesantren', 'lainnya' => 'Lainnya'];

    public const PUBLIK = ['siswa_id', 'anggota_kelas_id', 'nama_lengkap', 'nis', 'nisn', 'jenis_kelamin', 'tahun_masuk', 'tahun_lulus', 'tanggal_lulus', 'kelas_terakhir', 'status', 'status_penelusuran', 'jenis_sekolah', 'nama_sekolah', 'kota_sekolah', 'jurusan', 'tanggal_penelusuran'];

    public const PRIVAT = ['nomor_wa', 'email', 'catatan_penelusuran'];

    protected $fillable = ['token_pembuatan', ...self::PUBLIK, ...self::PRIVAT];

    protected $hidden = ['token_pembuatan', ...self::PRIVAT];

    protected $casts = ['siswa_id' => 'integer', 'anggota_kelas_id' => 'integer', 'tahun_masuk' => 'integer', 'tahun_lulus' => 'integer', 'versi' => 'integer',
        'tanggal_lulus' => 'date', 'tanggal_penelusuran' => 'date', 'kelulusan_dicatat_pada' => 'datetime',
        'nomor_wa' => 'encrypted', 'email' => 'encrypted', 'catatan_penelusuran' => 'encrypted'];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function anggotaKelas(): BelongsTo
    {
        return $this->belongsTo(AnggotaKelas::class);
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatAlumniHumas::class)->latest('id');
    }

    public function snapshot(): array
    {
        return array_replace($this->only(self::PUBLIK), ['tanggal_lulus' => $this->tanggal_lulus?->format('Y-m-d'), 'tanggal_penelusuran' => $this->tanggal_penelusuran?->format('Y-m-d')]);
    }
}
