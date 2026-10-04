<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramKomiteHumas extends Model
{
    protected $table = 'program_komite_humas';

    public const STATUS = ['rencana' => 'Direncanakan', 'berjalan' => 'Sedang berjalan', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];

    public const KOLOM = ['nama', 'tujuan', 'target_hasil', 'tanggal_mulai', 'tanggal_selesai', 'pengurus_komite_humas_id', 'status', 'capaian', 'catatan_evaluasi'];

    protected $fillable = ['token_pembuatan', ...self::KOLOM];

    protected $hidden = ['token_pembuatan'];

    protected $casts = ['tanggal_mulai' => 'date', 'tanggal_selesai' => 'date', 'diselesaikan_pada' => 'datetime', 'versi' => 'integer'];

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodeKomiteHumas::class, 'periode_komite_humas_id');
    }

    public function penanggungJawab(): BelongsTo
    {
        return $this->belongsTo(PengurusKomiteHumas::class, 'pengurus_komite_humas_id');
    }

    public function agenda(): BelongsToMany
    {
        return $this->belongsToMany(AgendaHumas::class, 'program_komite_humas_agenda', 'program_komite_humas_id', 'agenda_humas_id')->withTimestamps();
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatProgramKomiteHumas::class)->latest('id');
    }

    public function terlambat(): bool
    {
        return in_array($this->status, ['rencana', 'berjalan'], true) && $this->tanggal_selesai->lt(today());
    }

    public function snapshot(): array
    {
        return array_replace($this->only(self::KOLOM), [
            'tanggal_mulai' => $this->tanggal_mulai->format('d-m-Y'), 'tanggal_selesai' => $this->tanggal_selesai->format('d-m-Y'),
            'penanggung_jawab' => $this->penanggungJawab()->value('nama'),
            'rapat' => $this->agenda()->orderBy('agenda_humas.id')->get(['agenda_humas.id', 'judul', 'waktu_mulai', 'status'])
                ->map(fn ($a) => ['id' => $a->id, 'judul' => $a->judul, 'waktu' => $a->waktu_mulai->format('d-m-Y H:i'), 'status' => $a->status])->all(),
        ]);
    }
}
