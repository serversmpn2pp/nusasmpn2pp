<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengaturanInventaris extends Model
{
    protected $table = 'pengaturan_inventaris';

    protected $fillable = [
        'kode',
        'awalan_nomor_aset',
        'akhiran_nomor_aset',
        'nama_pemilik',
        'jumlah_digit_id_internal',
        'diperbarui_oleh_pengguna_id',
    ];

    protected $casts = [
        'jumlah_digit_id_internal' => 'integer',
        'diperbarui_oleh_pengguna_id' => 'integer',
    ];

    public static function utama(): self
    {
        return self::firstOrCreate(
            ['kode' => 'utama'],
            [
                'awalan_nomor_aset' => '12.03.15.08.10',
                'akhiran_nomor_aset' => '08',
                'nama_pemilik' => 'SMPN 2 Padang Panjang',
                'jumlah_digit_id_internal' => 6,
            ],
        );
    }

    public function diperbaruiOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diperbarui_oleh_pengguna_id');
    }

    public function contohNomorAset(?int $tahun = null): string
    {
        return trim($this->awalan_nomor_aset, '.')
            .'.'.($tahun ?: (int) now()->format('Y'))
            .'.'.trim($this->akhiran_nomor_aset, '.');
    }
}
