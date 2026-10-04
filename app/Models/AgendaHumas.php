<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AgendaHumas extends Model
{
    protected $table = 'agenda_humas';

    public const JENIS = [
        'orang_tua' => 'Pertemuan orang tua/wali',
        'komite' => 'Rapat komite sekolah',
        'koordinasi_eksternal' => 'Koordinasi eksternal / dinas',
        'kemitraan' => 'Pertemuan mitra sekolah',
        'internal' => 'Koordinasi internal',
        'lainnya' => 'Kegiatan Humas lainnya',
    ];

    public const STATUS = ['terjadwal' => 'Terjadwal', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];

    protected $fillable = [
        'judul', 'jenis', 'waktu_mulai', 'waktu_selesai', 'tempat', 'tautan_pertemuan',
        'sasaran', 'pemimpin', 'notulis', 'topik', 'pembahasan', 'keputusan', 'status',
        'alasan_pembatalan', 'diselesaikan_pada', 'dibuat_oleh_pengguna_id', 'diubah_oleh_pengguna_id',
    ];

    protected $hidden = ['token_presensi'];

    protected $casts = ['waktu_mulai' => 'datetime', 'waktu_selesai' => 'datetime', 'diselesaikan_pada' => 'datetime',
        'presensi_dibuka' => 'boolean', 'presensi_diubah_pada' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(fn (AgendaHumas $agenda) => $agenda->token_presensi ??= Str::random(64));
    }

    public function menerimaPresensiQr(): bool
    {
        return $this->status === 'terjadwal' && $this->presensi_dibuka;
    }

    public function rekapPresensi(): array
    {
        $jumlah = $this->peserta()->reorder()->selectRaw('status_kehadiran, COUNT(*) AS jumlah')
            ->groupBy('status_kehadiran')->pluck('jumlah', 'status_kehadiran');

        return collect(PesertaPertemuanHumas::KEHADIRAN)->map(fn ($label, $kode) => (int) ($jumlah[$kode] ?? 0))->all();
    }

    public function peserta(): HasMany
    {
        return $this->hasMany(PesertaPertemuanHumas::class)->orderBy('nama')->orderBy('id');
    }

    public function tindakLanjut(): HasMany
    {
        return $this->hasMany(TindakLanjutAgendaHumas::class)->orderBy('id');
    }

    public function dokumen(): BelongsToMany
    {
        return $this->belongsToMany(DokumenHumas::class, 'agenda_humas_dokumen')->withTimestamps();
    }

    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diubah_oleh_pengguna_id');
    }

    public function mitra(): BelongsToMany
    {
        return $this->belongsToMany(MitraHumas::class, 'mitra_humas_agenda')->withTimestamps();
    }

    public function programKomite(): BelongsToMany
    {
        return $this->belongsToMany(ProgramKomiteHumas::class, 'program_komite_humas_agenda', 'agenda_humas_id', 'program_komite_humas_id')->withTimestamps();
    }

    public function labelWaktu(): string
    {
        if ($this->status !== 'terjadwal') {
            return self::STATUS[$this->status];
        }

        return match (true) {
            now()->gt($this->waktu_selesai) => 'Jadwal lewat',
            now()->gte($this->waktu_mulai) => 'Sedang berlangsung',
            default => 'Akan datang',
        };
    }
}
