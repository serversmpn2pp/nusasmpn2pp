<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LampiranTindakLanjutSupervisi extends Model
{
    protected $table = 'lampiran_tindak_lanjut_supervisi';

    protected $guarded = ['id'];

    protected $hidden = ['lokasi_file'];

    public function tugas()
    {
        return $this->belongsTo(TindakLanjutSupervisi::class, 'tindak_lanjut_supervisi_id');
    }
}
