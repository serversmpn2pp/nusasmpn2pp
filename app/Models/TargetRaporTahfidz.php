<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TargetRaporTahfidz extends Model
{
    protected $table = 'target_rapor_tahfidz';

    protected $fillable = ['tahun_pelajaran_id', 'tingkat', 'target', 'mode', 'surah', 'tanggal_rapor',
        'batas_cukup', 'batas_baik', 'batas_sangat_baik'];

    protected $casts = ['surah' => 'array', 'tanggal_rapor' => 'date', 'target' => 'integer'];

    public function predikat(float $nilai): string
    {
        return match (true) {
            $nilai >= $this->batas_sangat_baik => 'Sangat Baik',
            $nilai >= $this->batas_baik => 'Baik',
            $nilai >= $this->batas_cukup => 'Cukup',
            default => 'Perlu Bimbingan',
        };
    }
}
