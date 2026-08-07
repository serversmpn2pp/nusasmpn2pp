<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pertanyaan_survei_pembelajaran', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 80)->unique();
            $table->text('pernyataan');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index(['aktif', 'urutan'], 'pertanyaan_survei_aktif_urutan');
        });

        Schema::table('survei_pembelajaran', function (Blueprint $table) {
            $table->json('snapshot_pertanyaan')->nullable()->after('jawaban');
        });

        $waktu = now();
        $pertanyaan = [
            ['kode' => 'kejelasan_materi', 'pernyataan' => 'Guru menjelaskan materi dengan jelas dan mudah dipahami.', 'urutan' => 1],
            ['kode' => 'keteraturan_pembelajaran', 'pernyataan' => 'Kegiatan pembelajaran berlangsung teratur dan sesuai tujuan.', 'urutan' => 2],
            ['kode' => 'kesempatan_bertanya', 'pernyataan' => 'Guru memberi kesempatan kepada siswa untuk bertanya dan berdiskusi.', 'urutan' => 3],
            ['kode' => 'umpan_balik', 'pernyataan' => 'Guru memberikan penjelasan atau umpan balik terhadap tugas dan hasil belajar.', 'urutan' => 4],
            ['kode' => 'sikap_guru', 'pernyataan' => 'Guru memperlakukan siswa dengan sopan, adil, dan menghargai.', 'urutan' => 5],
            ['kode' => 'manfaat_pembelajaran', 'pernyataan' => 'Pembelajaran membantu saya memahami dan menerapkan materi.', 'urutan' => 6],
        ];

        DB::table('pertanyaan_survei_pembelajaran')->insert(
            collect($pertanyaan)->map(fn (array $item) => [
                ...$item,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ])->all(),
        );

        $teksPerKode = collect($pertanyaan)->pluck('pernyataan', 'kode');
        DB::table('survei_pembelajaran')
            ->select(['id', 'jawaban'])
            ->orderBy('id')
            ->chunkById(100, function ($survei) use ($teksPerKode): void {
                foreach ($survei as $item) {
                    $jawaban = json_decode((string) $item->jawaban, true) ?: [];
                    $snapshot = collect(array_keys($jawaban))
                        ->mapWithKeys(fn (string $kode) => [
                            $kode => [
                                'pernyataan' => $teksPerKode->get($kode, $kode),
                                'urutan' => (int) (array_search($kode, array_keys($jawaban), true) + 1),
                            ],
                        ])
                        ->all();

                    DB::table('survei_pembelajaran')
                        ->where('id', $item->id)
                        ->update(['snapshot_pertanyaan' => json_encode($snapshot)]);
                }
            });

        DB::table('izin')->updateOrInsert(
            ['kode' => 'survei.pertanyaan_kelola'],
            [
                'kelompok' => 'Kurikulum',
                'nama' => 'Kelola pernyataan survei pembelajaran',
                'deskripsi' => 'Menambah, mengubah, mengurutkan, dan menonaktifkan pernyataan survei pembelajaran siswa.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
        );

        $izinId = DB::table('izin')->where('kode', 'survei.pertanyaan_kelola')->value('id');
        $peranId = DB::table('peran')->where('kode', 'wakil_pimpinan_kurikulum')->value('id');

        if ($izinId && $peranId) {
            DB::table('peran_izin')->insertOrIgnore([
                'peran_id' => $peranId,
                'izin_id' => $izinId,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ]);
        }
    }

    public function down(): void
    {
        $izinId = DB::table('izin')->where('kode', 'survei.pertanyaan_kelola')->value('id');

        if ($izinId) {
            DB::table('peran_izin')->where('izin_id', $izinId)->delete();
            DB::table('izin')->where('id', $izinId)->delete();
        }

        Schema::table('survei_pembelajaran', function (Blueprint $table) {
            $table->dropColumn('snapshot_pertanyaan');
        });

        Schema::dropIfExists('pertanyaan_survei_pembelajaran');
    }
};
