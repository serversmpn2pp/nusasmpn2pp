<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PertanyaanSurveiPembelajaran extends Model
{
    protected $table = 'pertanyaan_survei_pembelajaran';

    protected $fillable = [
        'kode',
        'pernyataan',
        'urutan',
        'aktif',
    ];

    protected $casts = [
        'urutan' => 'integer',
        'aktif' => 'boolean',
    ];

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    public function scopeTerurut(Builder $query): Builder
    {
        return $query->orderBy('urutan')->orderBy('id');
    }
}
