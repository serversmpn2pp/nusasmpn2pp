<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->selaraskanSkor([
            'mudah' => 1,
            'sedang' => 2,
            'sulit' => 3,
            'sangat_sulit' => 4,
        ]);
    }

    public function down(): void
    {
        DB::table('soal_cbt')
            ->where('tingkat_kesulitan', 'sangat_sulit')
            ->update(['tingkat_kesulitan' => 'sulit']);

        $this->selaraskanSkor([
            'mudah' => 1,
            'sedang' => 1,
            'sulit' => 1,
        ]);
    }

    private function selaraskanSkor(array $skorKesulitan): void
    {
        $ujianDikerjakan = DB::table('peserta_ujian_cbt')
            ->where(function ($query) {
                $query->whereNotNull('waktu_mulai')
                    ->orWhereIn('status', ['sedang_mengerjakan', 'selesai']);
            })
            ->pluck('ujian_cbt_id')
            ->merge(
                DB::table('jawaban_peserta_ujian_cbt')
                    ->join('soal_ujian_cbt', 'soal_ujian_cbt.id', '=', 'jawaban_peserta_ujian_cbt.soal_ujian_cbt_id')
                    ->pluck('soal_ujian_cbt.ujian_cbt_id'),
            )
            ->unique()
            ->values();

        foreach ($skorKesulitan as $kesulitan => $skor) {
            DB::table('soal_cbt')
                ->where('tingkat_kesulitan', $kesulitan)
                ->update(['skor_maksimal' => $skor]);

            DB::table('soal_ujian_cbt')
                ->whereIn('soal_cbt_id', function ($query) use ($kesulitan) {
                    $query->select('id')
                        ->from('soal_cbt')
                        ->where('tingkat_kesulitan', $kesulitan);
                })
                ->when($ujianDikerjakan->isNotEmpty(), fn ($query) => $query->whereNotIn('ujian_cbt_id', $ujianDikerjakan))
                ->update(['bobot' => $skor]);
        }
    }
};
