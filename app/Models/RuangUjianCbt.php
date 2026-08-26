<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RuangUjianCbt extends Model
{
    protected $table = 'ruang_ujian_cbt';

    public const DAFTAR_STATUS = [
        'draft' => 'Draft',
        'siap' => 'Siap',
        'berlangsung' => 'Berlangsung',
        'selesai' => 'Selesai',
        'nonaktif' => 'Nonaktif',
    ];

    public const DAFTAR_STATUS_BUKTI = [
        'belum_diunggah' => 'Belum diunggah',
        'sebagian' => 'Sebagian',
        'siap_dikirim' => 'Siap dikirim',
        'menunggu_pemeriksaan' => 'Menunggu pemeriksaan',
        'valid' => 'Lengkap dan valid',
        'perlu_diulang' => 'Perlu diulang',
    ];

    protected $fillable = [
        'ujian_cbt_id',
        'sesi_ujian_cbt_id',
        'jadwal_ujian_cbt_id',
        'ruang_kegiatan_ujian_cbt_id',
        'kode',
        'nama',
        'lokasi',
        'kapasitas',
        'pengawas_utama_pegawai_id',
        'pengawas_pendamping_pegawai_id',
        'waktu_mulai_aktual',
        'waktu_selesai_aktual',
        'berita_acara',
        'hambatan',
        'tindak_lanjut',
        'catatan',
        'dikunci_pada',
        'dikunci_oleh_pengguna_id',
        'bukti_daftar_hadir_lokasi_file',
        'bukti_daftar_hadir_nama_file_asli',
        'bukti_daftar_hadir_tipe_file',
        'bukti_daftar_hadir_ukuran_file',
        'bukti_daftar_hadir_diunggah_pada',
        'bukti_daftar_hadir_diunggah_oleh_pengguna_id',
        'bukti_berita_acara_lokasi_file',
        'bukti_berita_acara_nama_file_asli',
        'bukti_berita_acara_tipe_file',
        'bukti_berita_acara_ukuran_file',
        'bukti_berita_acara_diunggah_pada',
        'bukti_berita_acara_diunggah_oleh_pengguna_id',
        'status_bukti',
        'bukti_diajukan_pada',
        'bukti_diajukan_oleh_pengguna_id',
        'catatan_pemeriksaan_bukti',
        'bukti_diperiksa_pada',
        'bukti_diperiksa_oleh_pengguna_id',
        'status',
    ];

    protected $casts = [
        'kapasitas' => 'integer',
        'waktu_mulai_aktual' => 'datetime',
        'waktu_selesai_aktual' => 'datetime',
        'dikunci_pada' => 'datetime',
        'bukti_daftar_hadir_diunggah_pada' => 'datetime',
        'bukti_daftar_hadir_ukuran_file' => 'integer',
        'bukti_berita_acara_diunggah_pada' => 'datetime',
        'bukti_berita_acara_ukuran_file' => 'integer',
        'bukti_diajukan_pada' => 'datetime',
        'bukti_diperiksa_pada' => 'datetime',
    ];

    public function ujianCbt(): BelongsTo
    {
        return $this->belongsTo(UjianCbt::class);
    }

    public function sesiUjianCbt(): BelongsTo
    {
        return $this->belongsTo(SesiUjianCbt::class);
    }

    public function jadwalUjianCbt(): BelongsTo
    {
        return $this->belongsTo(JadwalUjianCbt::class);
    }

    public function ruangKegiatanUjianCbt(): BelongsTo
    {
        return $this->belongsTo(RuangKegiatanUjianCbt::class);
    }

    public function pengawasUtama(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pengawas_utama_pegawai_id');
    }

    public function pengawasPendamping(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pengawas_pendamping_pegawai_id');
    }

    public function dikunciOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dikunci_oleh_pengguna_id');
    }

    public function pesertaUjianCbt(): HasMany
    {
        return $this->hasMany(PesertaUjianCbt::class);
    }

    public function buktiDaftarHadirDiunggahOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'bukti_daftar_hadir_diunggah_oleh_pengguna_id');
    }

    public function buktiBeritaAcaraDiunggahOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'bukti_berita_acara_diunggah_oleh_pengguna_id');
    }

    public function buktiRuangUjianCbt(): HasMany
    {
        return $this->hasMany(BuktiRuangUjianCbt::class);
    }

    public function buktiDiajukanOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'bukti_diajukan_oleh_pengguna_id');
    }

    public function buktiDiperiksaOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'bukti_diperiksa_oleh_pengguna_id');
    }

    public function scopeDitugaskanKepada(Builder $query, int $pegawaiId): Builder
    {
        return $query->where(function (Builder $query) use ($pegawaiId) {
            $query->where('pengawas_utama_pegawai_id', $pegawaiId)
                ->orWhere('pengawas_pendamping_pegawai_id', $pegawaiId);
        });
    }

    public function labelStatusBukti(): string
    {
        return self::DAFTAR_STATUS_BUKTI[$this->status_bukti]
            ?? str($this->status_bukti)->headline()->toString();
    }

    public function labelStatus(): string
    {
        return self::DAFTAR_STATUS[$this->status] ?? str($this->status)->headline()->toString();
    }

    public function terkunci(): bool
    {
        return filled($this->dikunci_pada);
    }
}
