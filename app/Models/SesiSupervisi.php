<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SesiSupervisi extends Model
{
    public const STATUS = ['draf' => 'Draf', 'terjadwal' => 'Terjadwal', 'dibatalkan' => 'Dibatalkan'];

    protected $table = 'sesi_supervisi';

    protected $guarded = ['id'];

    protected $casts = ['versi' => 'integer', 'konteks' => 'array', 'waktu_mulai' => 'datetime', 'waktu_selesai' => 'datetime', 'diterbitkan_pada' => 'datetime'];

    public function program()
    {
        return $this->belongsTo(ProgramSupervisi::class, 'program_supervisi_id');
    }

    public function instrumen()
    {
        return $this->belongsTo(VersiInstrumenSupervisi::class, 'versi_instrumen_supervisi_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(Pegawai::class, 'supervisor_pegawai_id');
    }

    public function guru()
    {
        return $this->belongsTo(Pegawai::class, 'guru_pegawai_id');
    }

    public function praobservasi()
    {
        return $this->hasOne(PraobservasiSupervisi::class, 'sesi_supervisi_id');
    }

    public function penilaian()
    {
        return $this->hasOne(PenilaianSupervisi::class, 'sesi_supervisi_id');
    }

    public function pascaobservasi()
    {
        return $this->hasOne(PascaobservasiSupervisi::class, 'sesi_supervisi_id');
    }

    public function tindakLanjut()
    {
        return $this->hasMany(TindakLanjutSupervisi::class, 'sesi_supervisi_id');
    }
}
