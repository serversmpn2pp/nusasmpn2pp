<?php

namespace App\Http\Controllers;

use App\Models\KelasUjianCbt;
use App\Models\PesertaUjianCbt;
use App\Models\UjianCbt;
use App\Services\Cbt\AkunPesertaCbtService;
use App\Support\QrCodeSvg;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KartuPesertaUjianCbtController extends Controller
{
    public function index(Request $request, UjianCbt $ujianCbt, AkunPesertaCbtService $akunPesertaCbtService)
    {
        $data = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'status' => ['nullable', Rule::in(['semua', ...array_keys(PesertaUjianCbt::DAFTAR_STATUS)])],
        ]);

        $kelasId = $data['kelas_id'] ?? null;
        $status = $data['status'] ?? 'aktif';

        $ujianCbt->load(['jenisUjianCbt', 'tahunPelajaran']);
        $paketRangkaianIds = UjianCbt::query()
            ->where('jenis_ujian_cbt_id', $ujianCbt->jenis_ujian_cbt_id)
            ->where('tahun_pelajaran_id', $ujianCbt->tahun_pelajaran_id)
            ->where('semester', $ujianCbt->semester)
            ->where('tingkat', $ujianCbt->tingkat)
            ->pluck('id');

        $kelasPeserta = KelasUjianCbt::query()
            ->with('kelas')
            ->whereIn('ujian_cbt_id', $paketRangkaianIds)
            ->get()
            ->unique('kelas_id')
            ->sortBy(fn ($item) => $item->kelas?->nama)
            ->values();

        if ($kelasId && ! $kelasPeserta->contains('kelas_id', $kelasId)) {
            throw ValidationException::withMessages([
                'kelas_id' => 'Kelas yang dipilih bukan peserta rangkaian CBT ini.',
            ]);
        }

        $pesertaRangkaian = PesertaUjianCbt::query()
            ->with([
                'kelasUjianCbt.kelas',
                'anggotaKelas.siswa',
                'akunPesertaCbt',
            ])
            ->whereIn('ujian_cbt_id', $paketRangkaianIds)
            ->when($kelasId, fn ($query) => $query->whereHas(
                'kelasUjianCbt',
                fn ($query) => $query->where('kelas_id', $kelasId),
            ))
            ->when($status !== 'semua', fn ($query) => $query->where('status', $status))
            ->get();

        $pesertaRangkaian->each(function (PesertaUjianCbt $peserta, int $index) use ($ujianCbt, $akunPesertaCbtService) {
            if ($peserta->akunPesertaCbt) {
                return;
            }

            $akunPeserta = $akunPesertaCbtService->ambilAtauBuat(
                $ujianCbt,
                $peserta->kelasUjianCbt,
                $peserta->anggotaKelas,
                $index + 1,
            );

            $peserta->update(['akun_peserta_cbt_id' => $akunPeserta->id]);
            $peserta->setRelation('akunPesertaCbt', $akunPeserta);
        });

        $kartuPeserta = $pesertaRangkaian
            ->unique(fn (PesertaUjianCbt $peserta) => $peserta->akun_peserta_cbt_id ?: 'anggota-' . $peserta->anggota_kelas_id)
            ->sortBy(fn ($item) => sprintf(
                '%s|%05d|%s',
                $item->kelasUjianCbt?->kelas?->nama ?? '',
                $item->anggotaKelas?->nomor_absen ?? 999,
                $item->anggotaKelas?->siswa?->nama_lengkap ?? '',
            ))
            ->values()
            ->map(fn (PesertaUjianCbt $peserta) => [
                'peserta' => $peserta,
                'akun' => $peserta->akunPesertaCbt,
                'qr_svg' => QrCodeSvg::svg($peserta->akunPesertaCbt?->username ?: $peserta->username),
                'ukuran_font_nama' => $this->ukuranFontNama($peserta->anggotaKelas?->siswa?->nama_lengkap),
            ]);
        $daftarStatusPeserta = PesertaUjianCbt::DAFTAR_STATUS;

        return view('ujian-cbt.kartu-peserta.index', compact(
            'ujianCbt',
            'kelasPeserta',
            'kartuPeserta',
            'kelasId',
            'status',
            'daftarStatusPeserta',
        ));
    }

    private function ukuranFontNama(?string $nama): float
    {
        $panjang = mb_strlen(trim((string) $nama));

        return match (true) {
            $panjang <= 18 => 10.2,
            $panjang <= 24 => 8.8,
            $panjang <= 30 => 7.6,
            $panjang <= 38 => 6.6,
            default => 5.8,
        };
    }
}
