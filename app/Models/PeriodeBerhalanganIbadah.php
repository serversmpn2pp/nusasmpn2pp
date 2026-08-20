<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PeriodeBerhalanganIbadah extends Model
{
    public const STATUS_AKTIF = 'aktif';

    public const STATUS_PERLU_KONFIRMASI = 'perlu_konfirmasi';

    public const STATUS_SELESAI = 'selesai';

    protected $table = 'periode_berhalangan_ibadah';

    protected $fillable = [
        'tahun_pelajaran_id',
        'siswa_id',
        'kelas_id',
        'anggota_kelas_id',
        'tanggal_mulai',
        'tanggal_selesai',
        'status',
        'batas_hari_konfirmasi',
        'perlu_konfirmasi_sejak',
        'dimulai_oleh_pengguna_id',
        'diselesaikan_oleh_pengguna_id',
        'diselesaikan_pada',
        'cara_selesai',
        'catatan_privat',
        'terakhir_dikonfirmasi_pada',
        'terakhir_dikonfirmasi_oleh_pengguna_id',
        'konfirmasi_berikutnya_pada',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'batas_hari_konfirmasi' => 'integer',
        'perlu_konfirmasi_sejak' => 'date',
        'diselesaikan_pada' => 'datetime',
        'terakhir_dikonfirmasi_pada' => 'datetime',
        'konfirmasi_berikutnya_pada' => 'date',
    ];

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function anggotaKelas(): BelongsTo
    {
        return $this->belongsTo(AnggotaKelas::class);
    }

    public function dimulaiOlehPengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dimulai_oleh_pengguna_id');
    }

    public function diselesaikanOlehPengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diselesaikan_oleh_pengguna_id');
    }

    public function terakhirDikonfirmasiOlehPengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'terakhir_dikonfirmasi_oleh_pengguna_id');
    }

    public function presensiHarian(): HasMany
    {
        return $this->hasMany(PresensiBerhalanganIbadah::class);
    }

    public function logScan(): HasMany
    {
        return $this->hasMany(LogScanBerhalanganIbadah::class);
    }

    public function riwayatKonfirmasi(): HasMany
    {
        return $this->hasMany(KonfirmasiBerhalanganIbadah::class);
    }

    public function konfirmasiTerakhir(): HasOne
    {
        return $this->hasOne(KonfirmasiBerhalanganIbadah::class)->latestOfMany('dikonfirmasi_pada');
    }
}
