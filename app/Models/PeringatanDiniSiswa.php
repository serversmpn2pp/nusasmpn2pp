<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeringatanDiniSiswa extends Model
{
    public const DAFTAR_JENIS = [
        'mendekati_sanksi' => 'Mendekati Ambang Sanksi',
        'pelanggaran_berulang' => 'Pelanggaran Berulang',
        'sering_terlambat' => 'Sering Terlambat',
        'sanksi_belum_selesai' => 'Sanksi Belum Selesai',
    ];

    public const DAFTAR_TINGKAT = [
        'peringatan' => 'Peringatan',
        'penting' => 'Penting',
    ];

    public const DAFTAR_STATUS = [
        'aktif' => 'Aktif',
        'selesai' => 'Selesai',
    ];

    protected $table = 'peringatan_dini_siswa';

    protected $fillable = [
        'siswa_id',
        'tahun_pelajaran_id',
        'sanksi_poin_siswa_id',
        'jenis',
        'tingkat',
        'status',
        'kunci_unik',
        'judul',
        'pesan',
        'data_pendukung',
        'siklus',
        'terdeteksi_pada',
        'terakhir_terdeteksi_pada',
        'diselesaikan_pada',
    ];

    protected $casts = [
        'data_pendukung' => 'array',
        'siklus' => 'integer',
        'terdeteksi_pada' => 'datetime',
        'terakhir_terdeteksi_pada' => 'datetime',
        'diselesaikan_pada' => 'datetime',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function sanksiPoinSiswa(): BelongsTo
    {
        return $this->belongsTo(SanksiPoinSiswa::class);
    }

    public function pendampinganSiswa(): HasMany
    {
        return $this->hasMany(PendampinganSiswa::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status', 'aktif');
    }

    public function labelJenis(): string
    {
        return self::DAFTAR_JENIS[$this->jenis] ?? str($this->jenis)->headline()->toString();
    }

    public function labelTingkat(): string
    {
        return self::DAFTAR_TINGKAT[$this->tingkat] ?? str($this->tingkat)->headline()->toString();
    }

    public function labelStatus(): string
    {
        return self::DAFTAR_STATUS[$this->status] ?? str($this->status)->headline()->toString();
    }
}
