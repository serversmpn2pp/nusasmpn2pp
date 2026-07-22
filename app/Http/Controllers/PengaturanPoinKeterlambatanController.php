<?php

namespace App\Http\Controllers;

use App\Models\PengaturanPoinKeterlambatan;
use App\Models\TahunPelajaran;
use App\Services\Pembinaan\PengaturanPoinKeterlambatanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PengaturanPoinKeterlambatanController extends Controller
{
    public function __construct(private PengaturanPoinKeterlambatanService $pengaturanService) {}

    public function index()
    {
        $daftarTahunPelajaran = TahunPelajaran::query()
            ->with([
                'pengaturanPoinKeterlambatan.rentangPoinKeterlambatan',
                'pengaturanPoinKeterlambatan.diperbaruiOlehPengguna',
            ])
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->paginate(10);

        return view('pengaturan-poin-keterlambatan.index', compact('daftarTahunPelajaran'));
    }

    public function edit(TahunPelajaran $tahunPelajaran)
    {
        $pengaturan = $this->pengaturanService->nilaiUntukTahun($tahunPelajaran->id);

        return view('pengaturan-poin-keterlambatan.edit', compact('tahunPelajaran', 'pengaturan'));
    }

    public function update(Request $request, TahunPelajaran $tahunPelajaran)
    {
        $data = $request->validate([
            'aktif' => ['nullable', 'boolean'],
            'rentang' => ['required', 'array', 'min:1', 'max:20'],
            'rentang.*.menit_mulai' => ['required', 'integer', 'min:1', 'max:1440'],
            'rentang.*.menit_selesai' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'rentang.*.poin' => ['required', 'integer', 'min:0', 'max:500'],
        ]);

        $rentang = collect($data['rentang'])
            ->map(fn (array $item) => [
                'menit_mulai' => (int) $item['menit_mulai'],
                'menit_selesai' => filled($item['menit_selesai'] ?? null) ? (int) $item['menit_selesai'] : null,
                'poin' => (int) $item['poin'],
            ])
            ->sortBy('menit_mulai')
            ->values();

        $this->pastikanRentangValid($rentang);

        DB::transaction(function () use ($request, $tahunPelajaran, $rentang) {
            $pengaturan = PengaturanPoinKeterlambatan::updateOrCreate(
                ['tahun_pelajaran_id' => $tahunPelajaran->id],
                [
                    'aktif' => $request->boolean('aktif'),
                    'diperbarui_oleh_pengguna_id' => $request->user()?->id,
                ],
            );

            $pengaturan->rentangPoinKeterlambatan()->delete();
            $pengaturan->rentangPoinKeterlambatan()->createMany(
                $rentang->map(fn (array $item, int $index) => $item + ['urutan' => $index + 1])->all(),
            );
        });

        return redirect()->route('pengaturan-poin-keterlambatan.index')
            ->with('berhasil', 'Pengaturan poin keterlambatan tahun '.$tahunPelajaran->nama.' berhasil disimpan.');
    }

    private function pastikanRentangValid($rentang): void
    {
        if ((int) $rentang->first()['menit_mulai'] !== 1) {
            throw ValidationException::withMessages([
                'rentang.0.menit_mulai' => 'Rentang pertama harus dimulai dari menit ke-1.',
            ]);
        }

        foreach ($rentang as $index => $item) {
            $terakhir = $index === $rentang->count() - 1;

            if (! $terakhir && $item['menit_selesai'] === null) {
                throw ValidationException::withMessages([
                    "rentang.{$index}.menit_selesai" => 'Hanya rentang terakhir yang boleh tanpa batas akhir.',
                ]);
            }

            if ($terakhir && $item['menit_selesai'] !== null) {
                throw ValidationException::withMessages([
                    "rentang.{$index}.menit_selesai" => 'Rentang terakhir harus tanpa batas akhir.',
                ]);
            }

            if ($item['menit_selesai'] !== null && $item['menit_selesai'] < $item['menit_mulai']) {
                throw ValidationException::withMessages([
                    "rentang.{$index}.menit_selesai" => 'Batas akhir tidak boleh lebih kecil daripada batas awal.',
                ]);
            }

            if ($index > 0) {
                $sebelumnya = $rentang[$index - 1];
                if ($sebelumnya['menit_selesai'] === null || $item['menit_mulai'] !== $sebelumnya['menit_selesai'] + 1) {
                    throw ValidationException::withMessages([
                        "rentang.{$index}.menit_mulai" => 'Rentang menit harus berurutan tanpa celah atau tumpang tindih.',
                    ]);
                }
            }
        }
    }
}
