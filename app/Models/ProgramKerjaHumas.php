<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramKerjaHumas extends Model
{
    protected $table = 'program_kerja_humas';

    public const SEMESTER = ['ganjil' => 'Semester ganjil', 'genap' => 'Semester genap', 'tahunan' => 'Satu tahun pelajaran'];

    public const BIDANG = ['orang_tua' => 'Hubungan orang tua', 'komite' => 'Komite sekolah', 'kemitraan' => 'Kemitraan', 'publikasi' => 'Publikasi dan promosi', 'pengaduan' => 'Aspirasi dan pengaduan', 'alumni' => 'Alumni', 'kedinasan' => 'Administrasi kedinasan', 'lainnya' => 'Lainnya'];

    public const STATUS = ['rencana' => 'Direncanakan', 'berjalan' => 'Sedang berjalan', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];

    public const KOLOM = ['tahun_pelajaran_id', 'semester', 'bidang', 'nama', 'tujuan', 'sasaran', 'target_hasil', 'target_kegiatan', 'penanggung_jawab', 'tanggal_mulai', 'tanggal_selesai', 'status', 'evaluasi'];

    protected $fillable = ['token_pembuatan', ...self::KOLOM];

    protected $hidden = ['token_pembuatan'];

    protected $casts = ['tanggal_mulai' => 'date', 'tanggal_selesai' => 'date', 'target_kegiatan' => 'integer', 'versi' => 'integer'];

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function laporan(): HasMany
    {
        return $this->hasMany(LaporanPelaksanaanHumas::class);
    }

    public function laporanFinal(): HasMany
    {
        return $this->laporan()->where('status', 'final');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatProgramKerjaHumas::class)->latest('id');
    }

    public function terbuka(): bool
    {
        return in_array($this->status, ['rencana', 'berjalan'], true);
    }

    public function terlambat(): bool
    {
        return $this->terbuka() && $this->tanggal_selesai->lt(today());
    }

    public function snapshot(): array
    {
        return array_replace($this->only(self::KOLOM), ['tahun_pelajaran' => $this->tahunPelajaran()->value('nama'),
            'tanggal_mulai' => $this->tanggal_mulai->format('Y-m-d'), 'tanggal_selesai' => $this->tanggal_selesai->format('Y-m-d')]);
    }
}
