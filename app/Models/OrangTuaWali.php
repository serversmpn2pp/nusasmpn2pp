<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OrangTuaWali extends Model
{
    protected $table = 'orang_tua_wali';

    protected $fillable = [
        'pengguna_id',
        'siswa_acuan_username_id',
        'nama_lengkap',
        'nomor_wa',
    ];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class);
    }

    public function siswaAcuanUsername(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_acuan_username_id');
    }

    public function siswa(): BelongsToMany
    {
        return $this->belongsToMany(Siswa::class, 'orang_tua_wali_siswa')
            ->withPivot(['hubungan', 'utama'])
            ->withTimestamps();
    }
}
