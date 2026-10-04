<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PesertaPertemuanHumas extends Model
{
    protected $table = 'peserta_pertemuan_humas';

    public const KEHADIRAN = [
        'belum_dicatat' => 'Belum dicatat', 'hadir' => 'Hadir', 'izin' => 'Izin', 'tidak_hadir' => 'Tidak hadir',
    ];

    protected $fillable = ['nama', 'instansi', 'peran', 'status_kehadiran', 'catatan', 'hadir_pada', 'dicatat_oleh_pengguna_id',
        'orang_tua_wali_id', 'anak_undangan', 'sumber_kehadiran', 'versi_presensi'];

    protected $casts = ['hadir_pada' => 'datetime', 'anak_undangan' => 'array', 'versi_presensi' => 'integer'];

    public function orangTuaWali(): BelongsTo
    {
        return $this->belongsTo(OrangTuaWali::class);
    }

    public function agenda(): BelongsTo
    {
        return $this->belongsTo(AgendaHumas::class, 'agenda_humas_id');
    }
}
