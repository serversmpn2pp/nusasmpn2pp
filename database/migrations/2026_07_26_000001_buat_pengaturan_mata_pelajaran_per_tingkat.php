<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_mata_pelajaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_pelajaran_id')
                ->constrained('tahun_pelajaran')
                ->cascadeOnDelete();
            $table->foreignId('mata_pelajaran_id')
                ->constrained('mata_pelajaran')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('tingkat');
            $table->string('kode', 30);
            $table->unsignedSmallInteger('kkm')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->unique(
                ['tahun_pelajaran_id', 'mata_pelajaran_id', 'tingkat'],
                'pengaturan_mapel_unik_per_tahun_tingkat'
            );
            $table->unique(
                ['tahun_pelajaran_id', 'kode'],
                'pengaturan_mapel_kode_unik_per_tahun'
            );
            $table->index(
                ['tahun_pelajaran_id', 'tingkat', 'aktif'],
                'pengaturan_mapel_tahun_tingkat_aktif'
            );
        });

        $this->satukanMataPelajaranLama();
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_mata_pelajaran');
    }

    private function satukanMataPelajaranLama(): void
    {
        $mataPelajaran = DB::table('mata_pelajaran')->orderBy('id')->get();
        $tahunPelajaranIds = DB::table('tahun_pelajaran')->pluck('id');

        if ($mataPelajaran->isEmpty() || $tahunPelajaranIds->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($mataPelajaran, $tahunPelajaranIds) {
            $mataPelajaran
                ->groupBy(fn (object $item) => $this->kunciNama($item->nama))
                ->each(function (Collection $kelompok) use ($tahunPelajaranIds) {
                    $utama = $kelompok->sortBy('id')->first();
                    $namaUtama = $kelompok
                        ->map(fn (object $item) => $this->namaTanpaTingkat($item->nama))
                        ->sortByDesc(fn (string $nama) => mb_strlen($nama))
                        ->first();
                    $kodeUtama = $this->kodeTanpaTingkat($utama->kode);

                    foreach ($kelompok as $item) {
                        $tingkat = $this->ambilTingkat($item);

                        if (! $tingkat || blank($item->kode)) {
                            continue;
                        }

                        foreach ($tahunPelajaranIds as $tahunPelajaranId) {
                            DB::table('pengaturan_mata_pelajaran')->updateOrInsert(
                                [
                                    'tahun_pelajaran_id' => $tahunPelajaranId,
                                    'mata_pelajaran_id' => $utama->id,
                                    'tingkat' => $tingkat,
                                ],
                                [
                                    'kode' => mb_strtoupper(trim($item->kode)),
                                    'kkm' => $item->kkm,
                                    'aktif' => (bool) $item->aktif,
                                    'created_at' => $item->created_at ?? now(),
                                    'updated_at' => now(),
                                ],
                            );
                        }

                        if ((int) $item->id === (int) $utama->id) {
                            continue;
                        }

                        $this->pindahkanRelasi((int) $item->id, (int) $utama->id);
                        DB::table('mata_pelajaran')->where('id', $item->id)->delete();
                    }

                    DB::table('mata_pelajaran')
                        ->where('id', $utama->id)
                        ->update([
                            'kode' => $kodeUtama,
                            'nama' => $namaUtama,
                            'tingkat' => null,
                            'kkm' => null,
                            'aktif' => $kelompok->contains(fn (object $item) => (bool) $item->aktif),
                            'updated_at' => now(),
                        ]);
                });
        });
    }

    private function pindahkanRelasi(int $asalId, int $tujuanId): void
    {
        foreach ([
            'guru_mata_pelajaran',
            'perangkat_ajar',
            'ujian_omr',
            'soal_cbt',
            'ujian_cbt',
            'jadwal_ujian_cbt',
        ] as $tabel) {
            if (Schema::hasTable($tabel)) {
                DB::table($tabel)
                    ->where('mata_pelajaran_id', $asalId)
                    ->update(['mata_pelajaran_id' => $tujuanId]);
            }
        }
    }

    private function ambilTingkat(object $mataPelajaran): ?int
    {
        if (in_array((int) $mataPelajaran->tingkat, [7, 8, 9], true)) {
            return (int) $mataPelajaran->tingkat;
        }

        if (preg_match('/\s+(VII|VIII|IX)\s*$/iu', $mataPelajaran->nama, $cocok)) {
            return match (mb_strtoupper($cocok[1])) {
                'VII' => 7,
                'VIII' => 8,
                'IX' => 9,
            };
        }

        return null;
    }

    private function namaTanpaTingkat(string $nama): string
    {
        $nama = preg_replace('/\s+(VII|VIII|IX)\s*$/iu', '', trim($nama));
        $nama = preg_replace('/\s+/', ' ', $nama);
        $nama = preg_replace('/\s*\(\s*/', ' (', $nama);
        $nama = preg_replace('/\s*\)\s*/', ')', $nama);

        return trim($nama);
    }

    private function kunciNama(string $nama): string
    {
        $nama = preg_replace(
            '/\s*\((IPA|IPS|PJOK)\)\s*$/iu',
            '',
            $this->namaTanpaTingkat($nama),
        );

        return Str::slug($nama);
    }

    private function kodeTanpaTingkat(?string $kode): ?string
    {
        if (blank($kode)) {
            return null;
        }

        $kode = mb_strtoupper(trim($kode));
        $kodeDasar = preg_replace('/(?:VII|VIII|IX|7|8|9)$/iu', '', $kode);

        return filled($kodeDasar) ? $kodeDasar : $kode;
    }
};
