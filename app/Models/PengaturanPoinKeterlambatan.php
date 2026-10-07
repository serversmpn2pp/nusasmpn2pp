<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PengaturanPoinKeterlambatan extends Model
{
    protected $table = 'pengaturan_poin_keterlambatan';

    protected $fillable = [
        'tahun_pelajaran_id',
        'aktif',
        'otomatis_langsung',
        'berlaku_mulai',
        'poin_terlambat',
        'poin_alfa',
        'alfa_diproses_sampai',
        'diperbarui_oleh_pengguna_id',
    ];

    protected $casts = [
        'tahun_pelajaran_id' => 'integer',
        'aktif' => 'boolean',
        'otomatis_langsung' => 'boolean',
        'berlaku_mulai' => 'date',
        'poin_terlambat' => 'integer',
        'poin_alfa' => 'integer',
        'alfa_diproses_sampai' => 'date',
        'diperbarui_oleh_pengguna_id' => 'integer',
    ];

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function rentangPoinKeterlambatan(): HasMany
    {
        return $this->hasMany(RentangPoinKeterlambatan::class)->orderBy('urutan')->orderBy('menit_mulai');
    }

    public function diperbaruiOlehPengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diperbarui_oleh_pengguna_id');
    }
}
