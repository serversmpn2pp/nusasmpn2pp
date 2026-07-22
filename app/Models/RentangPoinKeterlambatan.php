<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentangPoinKeterlambatan extends Model
{
    protected $table = 'rentang_poin_keterlambatan';

    protected $fillable = [
        'pengaturan_poin_keterlambatan_id',
        'menit_mulai',
        'menit_selesai',
        'poin',
        'urutan',
    ];

    protected $casts = [
        'pengaturan_poin_keterlambatan_id' => 'integer',
        'menit_mulai' => 'integer',
        'menit_selesai' => 'integer',
        'poin' => 'integer',
        'urutan' => 'integer',
    ];

    public function pengaturanPoinKeterlambatan(): BelongsTo
    {
        return $this->belongsTo(PengaturanPoinKeterlambatan::class);
    }

    public function laporanPembinaanSiswa(): HasMany
    {
        return $this->hasMany(LaporanPembinaanSiswa::class);
    }

    public function labelRentang(): string
    {
        return $this->menit_selesai
            ? $this->menit_mulai.'-'.$this->menit_selesai.' menit'
            : $this->menit_mulai.' menit atau lebih';
    }
}
