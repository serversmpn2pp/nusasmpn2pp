<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JamPelajaran extends Model
{
    protected $table = 'jam_pelajaran';

    public const DAFTAR_HARI = [
        'senin' => 'Senin',
        'selasa' => 'Selasa',
        'rabu' => 'Rabu',
        'kamis' => 'Kamis',
        'jumat' => 'Jumat',
        'sabtu' => 'Sabtu',
        'minggu' => 'Minggu',
    ];

    public const DAFTAR_JENIS = [
        'pelajaran' => 'Pelajaran',
        'istirahat' => 'Istirahat',
        'upacara' => 'Upacara',
        'lainnya' => 'Lainnya',
    ];

    protected $fillable = [
        'hari',
        'nomor_jam',
        'label',
        'jam_mulai',
        'jam_selesai',
        'jenis',
        'aktif',
        'keterangan',
    ];

    protected $casts = [
        'nomor_jam' => 'integer',
        'aktif' => 'boolean',
    ];

    public function jadwalPelajaran(): HasMany
    {
        return $this->hasMany(JadwalPelajaran::class);
    }

    public function labelHari(): string
    {
        return self::DAFTAR_HARI[$this->hari] ?? str($this->hari)->headline()->toString();
    }

    public function labelJenis(): string
    {
        return self::DAFTAR_JENIS[$this->jenis] ?? str($this->jenis)->headline()->toString();
    }

    public function labelJam(): string
    {
        return ($this->label ?: 'Jam ' . $this->nomor_jam) . ' (' . $this->formatJam($this->jam_mulai) . '-' . $this->formatJam($this->jam_selesai) . ')';
    }

    public function formatJam(?string $jam): string
    {
        return $jam ? substr($jam, 0, 5) : '-';
    }
}
