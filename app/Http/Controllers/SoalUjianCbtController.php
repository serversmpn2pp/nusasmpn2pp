<?php

namespace App\Http\Controllers;

use App\Models\SoalCbt;
use App\Models\UjianCbt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SoalUjianCbtController extends Controller
{
    public function edit(UjianCbt $ujianCbt)
    {
        $ujianCbt->load([
            'jenisUjianCbt',
            'tahunPelajaran',
            'mataPelajaran',
            'soalUjianCbt.soalCbt.mataPelajaran',
        ]);

        $soalDipilih = $ujianCbt->soalUjianCbt
            ->keyBy('soal_cbt_id');

        $soalCbt = SoalCbt::query()
            ->with(['mataPelajaran', 'tahunPelajaran'])
            ->where('mata_pelajaran_id', $ujianCbt->mata_pelajaran_id)
            ->where('tingkat', $ujianCbt->tingkat)
            ->where(function ($query) use ($soalDipilih) {
                $query->where(function ($query) {
                    $query->where('aktif', true)
                        ->where('status', 'siap');
                })->orWhereIn('id', $soalDipilih->keys());
            })
            ->orderBy('jenis_soal')
            ->orderBy('tingkat_kesulitan')
            ->orderBy('kode')
            ->get();

        $ringkasanJenis = $ujianCbt->soalUjianCbt
            ->pluck('soalCbt')
            ->filter()
            ->groupBy('jenis_soal')
            ->map->count();
        $ringkasanKesulitan = $ujianCbt->soalUjianCbt
            ->pluck('soalCbt')
            ->filter()
            ->groupBy('tingkat_kesulitan')
            ->map->count();

        return view('ujian-cbt.soal.edit', [
            'ujianCbt' => $ujianCbt,
            'soalCbt' => $soalCbt,
            'soalDipilih' => $soalDipilih,
            'ringkasanJenis' => $ringkasanJenis,
            'ringkasanKesulitan' => $ringkasanKesulitan,
            'daftarJenisSoal' => SoalCbt::DAFTAR_JENIS,
            'daftarKesulitan' => SoalCbt::DAFTAR_KESULITAN,
        ]);
    }

    public function update(Request $request, UjianCbt $ujianCbt)
    {
        $this->pastikanPaketBelumDikerjakan($ujianCbt);

        $data = $request->validate([
            'soal' => ['nullable', 'array'],
            'soal.*.dipilih' => ['nullable', 'boolean'],
            'soal.*.nomor_urut' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $barisTerpilih = collect($data['soal'] ?? [])
            ->filter(fn ($item) => filter_var($item['dipilih'] ?? false, FILTER_VALIDATE_BOOLEAN))
            ->mapWithKeys(fn ($item, $soalId) => [
                (int) $soalId => [
                    'nomor_urut' => filled($item['nomor_urut'] ?? null) ? (int) $item['nomor_urut'] : null,
                ],
            ]);

        $this->pastikanSoalCocokDenganPaket($ujianCbt, $barisTerpilih->keys()->all());
        $skorSoal = SoalCbt::query()
            ->whereIn('id', $barisTerpilih->keys())
            ->pluck('skor_maksimal', 'id');

        DB::transaction(function () use ($ujianCbt, $barisTerpilih, $skorSoal) {
            $ujianCbt->soalUjianCbt()
                ->whereNotIn('soal_cbt_id', $barisTerpilih->keys())
                ->delete();

            foreach ($barisTerpilih as $soalId => $item) {
                $ujianCbt->soalUjianCbt()->updateOrCreate(
                    ['soal_cbt_id' => $soalId],
                    [
                        'nomor_urut' => $item['nomor_urut'],
                        'bobot' => (float) $skorSoal->get($soalId),
                    ],
                );
            }

            $ujianCbt->update(['jumlah_soal' => $barisTerpilih->count()]);
        });

        return redirect()
            ->route($ujianCbt->asesmenKelas() ? 'asesmen-kelas-cbt.show' : 'ujian-cbt.show', $ujianCbt)
            ->with('berhasil', $ujianCbt->asesmenKelas()
                ? 'Pilihan soal asesmen berhasil disimpan.'
                : 'Soal paket CBT berhasil diperbarui.');
    }

    private function pastikanPaketBelumDikerjakan(UjianCbt $ujianCbt): void
    {
        if ($ujianCbt->pesertaUjianCbt()->whereIn('status', ['sedang_mengerjakan', 'selesai'])->exists()) {
            throw ValidationException::withMessages([
                'soal' => 'Pilihan dan skor soal tidak dapat diubah karena ujian sudah dikerjakan peserta.',
            ]);
        }
    }

    private function pastikanSoalCocokDenganPaket(UjianCbt $ujianCbt, array $soalIds): void
    {
        if ($soalIds === []) {
            return;
        }

        $jumlahValid = SoalCbt::query()
            ->whereIn('id', $soalIds)
            ->where('mata_pelajaran_id', $ujianCbt->mata_pelajaran_id)
            ->where('tingkat', $ujianCbt->tingkat)
            ->where('aktif', true)
            ->where('status', 'siap')
            ->count();

        if ($jumlahValid !== count($soalIds)) {
            throw ValidationException::withMessages([
                'soal' => 'Ada soal yang tidak cocok dengan mata pelajaran, tingkat, atau belum berstatus siap.',
            ]);
        }
    }
}
