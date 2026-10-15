<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VersiInstrumenSupervisi extends Model
{
    protected $table = 'versi_instrumen_supervisi';

    protected $fillable = ['instrumen_supervisi_id', 'nomor', 'nama', 'versi', 'status', 'catatan', 'skala', 'kelompok', 'pertanyaan_refleksi', 'diterbitkan_pada', 'pengubah_id'];

    protected $casts = ['nomor' => 'integer', 'versi' => 'integer', 'skala' => 'array', 'kelompok' => 'array', 'pertanyaan_refleksi' => 'array', 'diterbitkan_pada' => 'datetime'];

    public function instrumen()
    {
        return $this->belongsTo(InstrumenSupervisi::class, 'instrumen_supervisi_id');
    }

    public function ringkasan(): array
    {
        $maksimal = max(array_column($this->skala, 'skor'));
        $hasil = [];
        foreach (['perencanaan', 'pelaksanaan'] as $tahap) {
            $jumlah = collect($this->kelompok)->where('tahap', $tahap)->sum(fn ($g) => count($g['indikator']));
            $hasil[$tahap] = ['jumlah_indikator' => $jumlah, 'skor_maksimal' => $jumlah * $maksimal, 'bobot' => 50];
        }

        return $hasil;
    }
}
