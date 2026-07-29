<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NilaiSiswa extends Model
{
    protected $table = 'nilai_siswa';

    protected $fillable = [
        'komponen_nilai_id',
        'siswa_id',
        'nilai',
        'predikat',
        'catatan',
    ];

    protected $casts = [
        'nilai' => 'decimal:2',
    ];

    public function komponenNilai(): BelongsTo
    {
        return $this->belongsTo(KomponenNilai::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }
}
