<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TindakLanjutAgendaHumas extends Model
{
    protected $table = 'tindak_lanjut_agenda_humas';

    public const STATUS = ['belum_mulai' => 'Belum mulai', 'diproses' => 'Diproses', 'selesai' => 'Selesai'];

    protected $fillable = ['uraian', 'penanggung_jawab', 'batas_tanggal', 'status', 'catatan', 'selesai_pada', 'diubah_oleh_pengguna_id'];

    protected $casts = ['batas_tanggal' => 'date', 'selesai_pada' => 'datetime'];

    public function agenda(): BelongsTo
    {
        return $this->belongsTo(AgendaHumas::class, 'agenda_humas_id');
    }

    public function terlambat(): bool
    {
        return $this->status !== 'selesai' && $this->batas_tanggal?->lt(today());
    }
}
