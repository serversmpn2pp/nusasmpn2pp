<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendampinganSiswa extends Model
{
    public const DAFTAR_JENIS = [
        'konseling' => 'Konseling Siswa',
        'pembinaan_wali' => 'Pembinaan Wali Kelas/Guru Wali',
        'pemanggilan_orang_tua' => 'Pemanggilan Orang Tua/Wali',
        'mediasi' => 'Mediasi',
        'lainnya' => 'Lainnya',
    ];

    public const DAFTAR_STATUS = [
        'dalam_proses' => 'Dalam Proses',
        'selesai' => 'Selesai',
    ];

    protected $table = 'pendampingan_siswa';

    protected $fillable = [
        'siswa_id',
        'tahun_pelajaran_id',
        'peringatan_dini_siswa_id',
        'petugas_pegawai_id',
        'jenis_tindakan',
        'tanggal_tindak_lanjut',
        'catatan',
        'status',
        'hasil',
        'selesai_pada',
        'kunci_aktif',
        'dibuat_oleh_pengguna_id',
        'diperbarui_oleh_pengguna_id',
    ];

    protected $casts = [
        'tanggal_tindak_lanjut' => 'date',
        'selesai_pada' => 'datetime',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function peringatanDiniSiswa(): BelongsTo
    {
        return $this->belongsTo(PeringatanDiniSiswa::class);
    }

    public function petugasPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'petugas_pegawai_id');
    }

    public function dibuatOlehPengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh_pengguna_id');
    }

    public function diperbaruiOlehPengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diperbarui_oleh_pengguna_id');
    }

    public function labelJenis(): string
    {
        return self::DAFTAR_JENIS[$this->jenis_tindakan]
            ?? str($this->jenis_tindakan)->replace('_', ' ')->title()->toString();
    }

    public function labelStatus(): string
    {
        return self::DAFTAR_STATUS[$this->status]
            ?? str($this->status)->replace('_', ' ')->title()->toString();
    }

    public static function kunciAktif(int $siswaId, int $tahunPelajaranId): string
    {
        return "siswa:{$siswaId}:tahun:{$tahunPelajaranId}";
    }
}
