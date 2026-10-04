<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaporanPelaksanaanHumas extends Model
{
    protected $table = 'laporan_pelaksanaan_humas';

    public const STATUS = ['draf' => 'Draf laporan', 'final' => 'Laporan final', 'dibatalkan' => 'Dibatalkan'];

    public const KOLOM = ['agenda_humas_id', 'judul', 'tanggal_mulai', 'tanggal_selesai', 'tempat', 'pelaksana', 'jumlah_peserta', 'uraian', 'hasil', 'kendala', 'tindak_lanjut'];

    protected $fillable = ['token_pembuatan', ...self::KOLOM];

    protected $hidden = ['token_pembuatan'];

    protected $casts = ['agenda_humas_id' => 'integer', 'tanggal_mulai' => 'date', 'tanggal_selesai' => 'date', 'jumlah_peserta' => 'integer', 'versi' => 'integer', 'snapshot_final' => 'array', 'difinalisasi_pada' => 'datetime'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(ProgramKerjaHumas::class, 'program_kerja_humas_id');
    }

    public function agenda(): BelongsTo
    {
        return $this->belongsTo(AgendaHumas::class, 'agenda_humas_id');
    }

    public function bukti(): HasMany
    {
        return $this->hasMany(BuktiLaporanHumas::class)->whereNull('dilepas_pada')->orderBy('id');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatProgramKerjaHumas::class)->latest('id');
    }

    public function snapshot(): array
    {
        return array_replace($this->only(self::KOLOM), ['tanggal_mulai' => $this->tanggal_mulai->format('Y-m-d'), 'tanggal_selesai' => $this->tanggal_selesai->format('Y-m-d'),
            'status' => $this->status, 'program' => $this->program()->firstOrFail()->snapshot(), 'agenda' => $this->agenda()->value('judul'),
            'bukti' => $this->bukti()->with('berkas')->get()->map(fn ($b) => ['id' => $b->id, 'judul' => $b->judul,
                'riwayat_dokumen_humas_id' => $b->riwayat_dokumen_humas_id, 'nama_file_asli' => $b->berkas->nama_file_asli, 'versi_dokumen' => $b->berkas->versi])->all()]);
    }
}
