<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublikasiNilaiSiswa extends Model
{
    protected $table = 'publikasi_nilai_siswa';

    protected $fillable = [
        'guru_mata_pelajaran_id',
        'semester',
        'dipublikasikan',
        'dipublikasikan_pada',
        'dipublikasikan_oleh_pengguna_id',
    ];

    protected $casts = [
        'dipublikasikan' => 'boolean',
        'dipublikasikan_pada' => 'datetime',
    ];

    public function guruMataPelajaran(): BelongsTo
    {
        return $this->belongsTo(GuruMataPelajaran::class);
    }

    public function dipublikasikanOlehPengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dipublikasikan_oleh_pengguna_id');
    }
}
