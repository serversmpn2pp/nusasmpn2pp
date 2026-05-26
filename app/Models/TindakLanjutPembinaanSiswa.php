<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TindakLanjutPembinaanSiswa extends Model
{
    protected $table = 'tindak_lanjut_pembinaan_siswa';

    public const DAFTAR_JENIS = [
        'konseling_siswa' => 'Konseling Siswa',
        'pemanggilan_siswa' => 'Pemanggilan Siswa',
        'pemanggilan_orang_tua' => 'Pemanggilan Orang Tua/Wali',
        'mediasi' => 'Mediasi',
        'kunjungan_rumah' => 'Kunjungan Rumah',
        'koordinasi_wali_kelas' => 'Koordinasi Wali Kelas',
        'koordinasi_guru_mapel' => 'Koordinasi Guru Mapel',
        'koordinasi_pimpinan' => 'Koordinasi Pimpinan',
        'keputusan_akhir' => 'Keputusan Akhir',
        'lainnya' => 'Lainnya',
    ];

    public const DAFTAR_STATUS_LAPORAN = [
        'diproses' => 'Diproses',
        'perlu_tindak_lanjut' => 'Perlu Tindak Lanjut',
        'selesai' => 'Selesai',
    ];

    protected $fillable = [
        'laporan_pembinaan_siswa_id',
        'tanggal_tindak_lanjut',
        'waktu_tindak_lanjut',
        'jenis_tindak_lanjut',
        'petugas_pegawai_id',
        'pihak_terlibat',
        'ringkasan',
        'hasil',
        'rencana_lanjutan',
        'status_laporan',
        'catatan_rahasia',
        'dibuat_oleh_pengguna_id',
    ];

    protected $casts = [
        'tanggal_tindak_lanjut' => 'date',
        'laporan_pembinaan_siswa_id' => 'integer',
        'petugas_pegawai_id' => 'integer',
        'dibuat_oleh_pengguna_id' => 'integer',
    ];

    public function laporanPembinaanSiswa(): BelongsTo
    {
        return $this->belongsTo(LaporanPembinaanSiswa::class);
    }

    public function petugasPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'petugas_pegawai_id');
    }

    public function dibuatOlehPengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh_pengguna_id');
    }

    public function labelJenis(): string
    {
        return self::DAFTAR_JENIS[$this->jenis_tindak_lanjut] ?? str($this->jenis_tindak_lanjut)->headline()->toString();
    }

    public function labelStatusLaporan(): string
    {
        return self::DAFTAR_STATUS_LAPORAN[$this->status_laporan]
            ?? LaporanPembinaanSiswa::DAFTAR_STATUS[$this->status_laporan]
            ?? str($this->status_laporan)->headline()->toString();
    }

    public function waktuTindakLanjutRingkas(): ?string
    {
        return filled($this->waktu_tindak_lanjut) ? substr((string) $this->waktu_tindak_lanjut, 0, 5) : null;
    }
}
