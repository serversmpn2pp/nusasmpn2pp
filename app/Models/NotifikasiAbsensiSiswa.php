<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotifikasiAbsensiSiswa extends Model
{
    protected $table = 'notifikasi_absensi_siswa';

    public const STATUS_MENUNGGU = 'menunggu';
    public const STATUS_SIMULASI = 'simulasi';
    public const STATUS_TERKIRIM = 'terkirim';
    public const STATUS_GAGAL = 'gagal';
    public const STATUS_DILEWATI = 'dilewati';

    public const DAFTAR_STATUS = [
        self::STATUS_MENUNGGU => 'Menunggu',
        self::STATUS_SIMULASI => 'Simulasi',
        self::STATUS_TERKIRIM => 'Terkirim',
        self::STATUS_GAGAL => 'Gagal',
        self::STATUS_DILEWATI => 'Dilewati',
    ];

    protected $fillable = [
        'absensi_siswa_id',
        'log_scan_absensi_id',
        'siswa_id',
        'tanggal',
        'jenis_absensi',
        'jenis_pesan',
        'kanal',
        'mode_pengiriman',
        'nomor_tujuan',
        'nama_penerima',
        'status',
        'pesan',
        'payload',
        'respons',
        'pesan_error',
        'dijadwalkan_pada',
        'dikirim_pada',
        'gagal_pada',
        'jumlah_percobaan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'payload' => 'array',
        'respons' => 'array',
        'dijadwalkan_pada' => 'datetime',
        'dikirim_pada' => 'datetime',
        'gagal_pada' => 'datetime',
        'jumlah_percobaan' => 'integer',
    ];

    public function absensiSiswa(): BelongsTo
    {
        return $this->belongsTo(AbsensiSiswa::class);
    }

    public function logScanAbsensi(): BelongsTo
    {
        return $this->belongsTo(LogScanAbsensi::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function labelStatus(): string
    {
        return self::DAFTAR_STATUS[$this->status] ?? str($this->status)->headline()->toString();
    }
}
