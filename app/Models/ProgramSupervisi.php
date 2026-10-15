<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramSupervisi extends Model
{
    public const STATUS = ['draf' => 'Draf', 'aktif' => 'Aktif', 'diarsipkan' => 'Diarsipkan'];

    protected $table = 'program_supervisi';

    protected $guarded = ['id'];

    protected $casts = ['versi' => 'integer', 'tanggal_mulai' => 'date', 'tanggal_selesai' => 'date'];

    public function tahunPelajaran()
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function sesi()
    {
        return $this->hasMany(SesiSupervisi::class);
    }
}
