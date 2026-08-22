<?php

namespace App\Http\Controllers;

use App\Models\JawabanPesertaUjianCbt;
use App\Models\PesertaUjianCbt;
use App\Models\SesiUjianCbt;
use App\Models\SoalUjianCbt;
use App\Models\UjianCbt;
use App\Services\Cbt\KoreksiOtomatisCbtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KoreksiManualUjianCbtController extends Controller
{
    public function index(Request $request, UjianCbt $ujianCbt)
    {
        $data = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'sesi_ujian_cbt_id' => ['nullable', 'integer', 'exists:sesi_ujian_cbt,id'],
            'status_koreksi' => ['nullable', Rule::in(['semua', 'belum_dikoreksi', 'sudah_dikoreksi'])],
        ]);

        $kelasId = $data['kelas_id'] ?? null;
        $sesiUjianCbtId = $data['sesi_ujian_cbt_id'] ?? null;
        $statusKoreksi = $data['status_koreksi'] ?? 'semua';

        $ujianCbt->load([
            'jenisUjianCbt',
            'tahunPelajaran',
            'mataPelajaran',
            'kelasUjianCbt.kelas',
            'sesiUjianCbt',
        ]);

        $soalManual = $this->ambilSoalManual($ujianCbt);
        $kelasPeserta = $ujianCbt->kelasUjianCbt
            ->sortBy(fn ($item) => $item->kelas?->nama)
            ->values();
        $sesiUjianCbt = $ujianCbt->sesiUjianCbt
            ->sortBy(fn (SesiUjianCbt $sesi) => sprintf(
                '%s|%s',
                $sesi->waktu_mulai?->format('YmdHis') ?? '99999999999999',
                $sesi->kode,
            ))
            ->values();

        $pesertaUjian = $ujianCbt->pesertaUjianCbt()
            ->with([
                'sesiUjianCbt',
                'kelasUjianCbt.kelas',
                'anggotaKelas.siswa',
                'jawabanPesertaUjianCbt' => fn ($query) => $query->whereIn('soal_ujian_cbt_id', $soalManual->pluck('id')),
            ])
            ->when($kelasId, fn ($query) => $query->whereHas(
                'kelasUjianCbt',
                fn ($query) => $query->where('kelas_id', $kelasId),
            ))
            ->when($sesiUjianCbtId, fn ($query) => $query->where('sesi_ujian_cbt_id', $sesiUjianCbtId))
            ->get()
            ->sortBy(fn (PesertaUjianCbt $item) => sprintf(
                '%s|%s|%05d|%s',
                $item->sesiUjianCbt?->kode ?? '',
                $item->kelasUjianCbt?->kelas?->nama ?? '',
                $item->anggotaKelas?->nomor_absen ?? 999,
                $item->anggotaKelas?->siswa?->nama_lengkap ?? '',
            ))
            ->values();

        $barisKoreksi = $this->susunBarisKoreksi($pesertaUjian, $soalManual, $statusKoreksi);

        return view('ujian-cbt.koreksi-manual.index', [
            'ujianCbt' => $ujianCbt,
            'kelasPeserta' => $kelasPeserta,
            'sesiUjianCbt' => $sesiUjianCbt,
            'soalManual' => $soalManual,
            'barisKoreksi' => $barisKoreksi,
            'ringkasan' => $this->ringkasan($barisKoreksi),
            'kelasId' => $kelasId,
            'sesiUjianCbtId' => $sesiUjianCbtId,
            'statusKoreksi' => $statusKoreksi,
        ]);
    }

    public function update(Request $request, UjianCbt $ujianCbt)
    {
        $data = $request->validate([
            'skor' => ['nullable', 'array'],
            'skor.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $nilaiSkor = collect($data['skor'] ?? []);

        if ($nilaiSkor->isEmpty()) {
            return back()->with('berhasil', 'Belum ada skor manual yang diubah.');
        }

        $soalManual = $this->ambilSoalManual($ujianCbt)->keyBy('id');
        $jawaban = JawabanPesertaUjianCbt::query()
            ->whereIn('id', $nilaiSkor->keys()->map(fn ($id) => (int) $id))
            ->whereHas('pesertaUjianCbt', fn ($query) => $query->where('ujian_cbt_id', $ujianCbt->id))
            ->get()
            ->keyBy('id');

        $errors = [];

        foreach ($nilaiSkor as $jawabanId => $skor) {
            $jawabanPeserta = $jawaban->get((int) $jawabanId);

            if (! $jawabanPeserta || ! $soalManual->has($jawabanPeserta->soal_ujian_cbt_id)) {
                $errors["skor.{$jawabanId}"] = 'Jawaban tidak valid untuk koreksi manual paket ini.';

                continue;
            }

            $relasiSoal = $soalManual->get($jawabanPeserta->soal_ujian_cbt_id);
            $skorMaksimal = (float) $relasiSoal->bobot;

            if (filled($skor) && round((float) $skor, 2) > $skorMaksimal) {
                $errors["skor.{$jawabanId}"] = "Skor tidak boleh lebih dari {$skorMaksimal}.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($nilaiSkor, $jawaban, $soalManual) {
            foreach ($nilaiSkor as $jawabanId => $skor) {
                $jawabanPeserta = $jawaban->get((int) $jawabanId);
                $relasiSoal = $soalManual->get($jawabanPeserta->soal_ujian_cbt_id);
                $skorMaksimal = (float) $relasiSoal->bobot;

                if (! filled($skor)) {
                    $jawabanPeserta->update([
                        'skor' => null,
                        'benar' => null,
                    ]);

                    continue;
                }

                $skor = round((float) $skor, 2);
                $jawabanPeserta->update([
                    'skor' => $skor,
                    'benar' => $skorMaksimal > 0 && $skor >= $skorMaksimal,
                ]);
            }
        });

        return back()->with('berhasil', 'Koreksi manual berhasil disimpan.');
    }

    private function ambilSoalManual(UjianCbt $ujianCbt)
    {
        return $ujianCbt->soalUjianCbt()
            ->with('soalCbt')
            ->get()
            ->sortBy(fn (SoalUjianCbt $item) => sprintf('%05d|%08d', $item->nomor_urut ?? 9999, $item->id))
            ->values()
            ->take($ujianCbt->jumlah_soal)
            ->reject(fn (SoalUjianCbt $item) => in_array($item->soalCbt?->jenis_soal, KoreksiOtomatisCbtService::JENIS_OTOMATIS, true))
            ->values();
    }

    private function susunBarisKoreksi($pesertaUjian, $soalManual, string $statusKoreksi)
    {
        return $pesertaUjian
            ->flatMap(function (PesertaUjianCbt $peserta) use ($soalManual) {
                $jawaban = $peserta->jawabanPesertaUjianCbt->keyBy('soal_ujian_cbt_id');

                return $soalManual->map(function (SoalUjianCbt $relasiSoal) use ($peserta, $jawaban) {
                    $jawabanPeserta = $jawaban->get($relasiSoal->id);
                    $sudahDijawab = $jawabanPeserta && ! is_null($jawabanPeserta->jawaban);
                    $sudahDikoreksi = $jawabanPeserta && ! is_null($jawabanPeserta->skor);

                    return [
                        'peserta' => $peserta,
                        'relasi_soal' => $relasiSoal,
                        'jawaban' => $jawabanPeserta,
                        'teks_jawaban' => $this->teksJawaban($jawabanPeserta?->jawaban),
                        'sudah_dijawab' => $sudahDijawab,
                        'sudah_dikoreksi' => $sudahDikoreksi,
                    ];
                });
            })
            ->filter(fn ($item) => match ($statusKoreksi) {
                'belum_dikoreksi' => $item['sudah_dijawab'] && ! $item['sudah_dikoreksi'],
                'sudah_dikoreksi' => $item['sudah_dikoreksi'],
                default => true,
            })
            ->values();
    }

    private function teksJawaban(?array $jawaban): string
    {
        if ($jawaban === null || $jawaban === []) {
            return '';
        }

        return collect($jawaban)
            ->flatten()
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->implode("\n");
    }

    private function ringkasan($barisKoreksi): array
    {
        return [
            'total' => $barisKoreksi->count(),
            'terjawab' => $barisKoreksi->where('sudah_dijawab', true)->count(),
            'belum_dijawab' => $barisKoreksi->where('sudah_dijawab', false)->count(),
            'belum_dikoreksi' => $barisKoreksi
                ->filter(fn ($item) => $item['sudah_dijawab'] && ! $item['sudah_dikoreksi'])
                ->count(),
            'sudah_dikoreksi' => $barisKoreksi->where('sudah_dikoreksi', true)->count(),
        ];
    }
}
