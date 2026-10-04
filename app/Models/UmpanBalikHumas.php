<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UmpanBalikHumas extends Model
{
    public const STATUS = ['draf' => 'Draf', 'aktif' => 'Dibuka', 'ditutup' => 'Ditutup', 'arsip' => 'Arsip'];

    public const CAKUPAN = ['seluruh' => 'Seluruh orang tua', 'tingkat' => 'Per tingkat', 'kelas' => 'Kelas terpilih', 'agenda' => 'Undangan pertemuan'];

    public const KOLOM = ['tahun_pelajaran_id', 'agenda_humas_id', 'judul', 'pengantar', 'penanggung_jawab', 'cakupan', 'tingkat', 'kelas_ids', 'mulai_pada', 'selesai_pada'];

    protected $table = 'umpan_balik_humas';

    protected $fillable = ['token_pembuatan', ...self::KOLOM];

    protected $hidden = ['token_pembuatan'];

    protected $casts = ['kelas_ids' => 'array', 'mulai_pada' => 'datetime', 'selesai_pada' => 'datetime', 'dibuka_pada' => 'datetime', 'versi' => 'integer'];

    public function pertanyaan(): HasMany
    {
        return $this->hasMany(PertanyaanUmpanBalikHumas::class)->orderBy('urutan')->orderBy('id');
    }

    public function sasaran(): HasMany
    {
        return $this->hasMany(SasaranUmpanBalikHumas::class);
    }

    public function tindakLanjut(): HasMany
    {
        return $this->hasMany(TindakLanjutUmpanBalikHumas::class)->orderBy('id');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatUmpanBalikHumas::class)->orderByDesc('id');
    }

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function agenda(): BelongsTo
    {
        return $this->belongsTo(AgendaHumas::class, 'agenda_humas_id');
    }

    public function menerimaJawaban(): bool
    {
        return $this->status === 'aktif' && now()->gte($this->mulai_pada) && now()->lt($this->selesai_pada);
    }

    public function labelStatus(): string
    {
        if ($this->status !== 'aktif') {
            return self::STATUS[$this->status];
        }

        return now()->lt($this->mulai_pada) ? 'Belum dimulai' : (now()->gte($this->selesai_pada) ? 'Periode berakhir' : 'Menerima jawaban');
    }
}
