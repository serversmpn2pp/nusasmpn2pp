<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\KelasUjianCbt;
use App\Models\PesertaUjianCbt;
use App\Models\SesiUjianCbt;
use App\Models\UjianCbt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PesertaUjianCbtController extends Controller
{
    public function index(Request $request, UjianCbt $ujianCbt)
    {
        $data = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'sesi_ujian_cbt_id' => ['nullable', 'integer', 'exists:sesi_ujian_cbt,id'],
            'status' => ['nullable', Rule::in(['semua', ...array_keys(PesertaUjianCbt::DAFTAR_STATUS)])],
        ]);

        $kelasId = $data['kelas_id'] ?? null;
        $sesiUjianCbtId = $data['sesi_ujian_cbt_id'] ?? null;
        $status = $data['status'] ?? 'semua';

        $ujianCbt->load([
            'jenisUjianCbt',
            'tahunPelajaran',
            'mataPelajaran',
        ]);

        $kelasPeserta = $ujianCbt->kelasUjianCbt()
            ->with(['kelas', 'pesertaUjianCbt'])
            ->get()
            ->sortBy(fn ($item) => $item->kelas?->nama)
            ->values();

        $sesiUjianCbt = $ujianCbt->sesiUjianCbt()
            ->withCount('pesertaUjianCbt')
            ->orderByRaw('waktu_mulai IS NULL')
            ->orderBy('waktu_mulai')
            ->orderBy('kode')
            ->get();

        $pesertaUjianCbt = $ujianCbt->pesertaUjianCbt()
            ->with([
                'sesiUjianCbt',
                'kelasUjianCbt.kelas',
                'anggotaKelas.siswa',
            ])
            ->when($kelasId, fn ($query) => $query->whereHas(
                'kelasUjianCbt',
                fn ($query) => $query->where('kelas_id', $kelasId),
            ))
            ->when($sesiUjianCbtId, fn ($query) => $query->where('sesi_ujian_cbt_id', $sesiUjianCbtId))
            ->when($status !== 'semua', fn ($query) => $query->where('status', $status))
            ->get()
            ->sortBy(fn ($item) => sprintf(
                '%s|%05d|%s',
                $item->kelasUjianCbt?->kelas?->nama ?? '',
                $item->anggotaKelas?->nomor_absen ?? 999,
                $item->anggotaKelas?->siswa?->nama_lengkap ?? '',
            ))
            ->values();

        $ringkasanStatus = $ujianCbt->pesertaUjianCbt()
            ->select('status', DB::raw('count(*) as jumlah'))
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        return view('ujian-cbt.peserta.index', [
            'ujianCbt' => $ujianCbt,
            'kelasPeserta' => $kelasPeserta,
            'sesiUjianCbt' => $sesiUjianCbt,
            'pesertaUjianCbt' => $pesertaUjianCbt,
            'ringkasanStatus' => $ringkasanStatus,
            'kelasId' => $kelasId,
            'sesiUjianCbtId' => $sesiUjianCbtId,
            'status' => $status,
            'daftarStatusPeserta' => PesertaUjianCbt::DAFTAR_STATUS,
            'daftarStatusSesi' => SesiUjianCbt::DAFTAR_STATUS,
        ]);
    }

    public function storeMassal(Request $request, UjianCbt $ujianCbt)
    {
        $this->pastikanDapatGenerate($ujianCbt);

        $kelasPeserta = $ujianCbt->kelasUjianCbt()
            ->with('kelas')
            ->orderBy('kelas_id')
            ->get();
        $sesiUjianCbt = $this->pastikanAdaSesi($ujianCbt);
        $pemakaianSesi = $sesiUjianCbt->mapWithKeys(fn ($sesi) => [
            $sesi->id => $sesi->peserta_ujian_cbt_count,
        ]);
        $jumlahBaru = 0;

        DB::transaction(function () use ($request, $ujianCbt, $kelasPeserta, $sesiUjianCbt, $pemakaianSesi, &$jumlahBaru) {
            foreach ($kelasPeserta as $kelasUjianCbt) {
                $anggotaKelas = AnggotaKelas::query()
                    ->with('siswa')
                    ->where('tahun_pelajaran_id', $ujianCbt->tahun_pelajaran_id)
                    ->where('kelas_id', $kelasUjianCbt->kelas_id)
                    ->where('status_keanggotaan', 'aktif')
                    ->whereHas('siswa', fn ($query) => $query->where('aktif', true))
                    ->orderByRaw('nomor_absen IS NULL')
                    ->orderBy('nomor_absen')
                    ->orderBy('id')
                    ->get();

                foreach ($anggotaKelas as $index => $anggota) {
                    $sudahAda = PesertaUjianCbt::query()
                        ->where('ujian_cbt_id', $ujianCbt->id)
                        ->where('anggota_kelas_id', $anggota->id)
                        ->exists();

                    if ($sudahAda) {
                        continue;
                    }

                    $sesiTerpilih = $this->pilihSesi($sesiUjianCbt, $pemakaianSesi);
                    $nomorPeserta = $this->buatNomorPeserta($ujianCbt, $kelasUjianCbt, $anggota, $index + 1);

                    PesertaUjianCbt::create([
                        'ujian_cbt_id' => $ujianCbt->id,
                        'sesi_ujian_cbt_id' => $sesiTerpilih?->id,
                        'kelas_ujian_cbt_id' => $kelasUjianCbt->id,
                        'anggota_kelas_id' => $anggota->id,
                        'nomor_peserta' => $nomorPeserta,
                        'status' => 'aktif',
                        'menit_tersisa' => $ujianCbt->durasi_menit,
                        'dibuat_oleh_pengguna_id' => $request->user()?->id,
                    ]);

                    if ($sesiTerpilih) {
                        $pemakaianSesi[$sesiTerpilih->id] = ($pemakaianSesi[$sesiTerpilih->id] ?? 0) + 1;
                    }

                    $jumlahBaru++;
                }
            }
        });

        $jumlahTotal = $ujianCbt->pesertaUjianCbt()->count();
        $pesan = $jumlahBaru
            ? "{$jumlahBaru} siswa berhasil dimasukkan sebagai peserta. Total {$jumlahTotal} peserta siap diatur."
            : "Seluruh siswa aktif di kelas yang dipilih sudah terdaftar. Total {$jumlahTotal} peserta.";

        return redirect()
            ->route('ujian-cbt.peserta.index', $ujianCbt)
            ->with('berhasil', $pesan);
    }

    public function update(Request $request, UjianCbt $ujianCbt)
    {
        $data = $request->validate([
            'peserta' => ['nullable', 'array'],
            'peserta.*.sesi_ujian_cbt_id' => ['nullable', 'integer', 'exists:sesi_ujian_cbt,id'],
            'peserta.*.status' => ['required', Rule::in(array_keys(PesertaUjianCbt::DAFTAR_STATUS))],
            'peserta.*.catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        $baris = collect($data['peserta'] ?? []);

        if ($baris->isEmpty()) {
            return redirect()
                ->route('ujian-cbt.peserta.index', $ujianCbt)
                ->with('berhasil', 'Belum ada peserta yang perlu diperbarui.');
        }

        $peserta = $ujianCbt->pesertaUjianCbt()
            ->whereIn('id', $baris->keys())
            ->get()
            ->keyBy('id');
        $sesiValid = $ujianCbt->sesiUjianCbt()
            ->whereIn('id', $baris->pluck('sesi_ujian_cbt_id')->filter()->unique())
            ->pluck('id')
            ->all();

        if ($peserta->count() !== $baris->count()) {
            throw ValidationException::withMessages([
                'peserta' => 'Ada peserta yang tidak termasuk dalam paket CBT ini.',
            ]);
        }

        DB::transaction(function () use ($baris, $peserta, $sesiValid) {
            foreach ($baris as $pesertaId => $item) {
                $sesiId = filled($item['sesi_ujian_cbt_id'] ?? null) ? (int) $item['sesi_ujian_cbt_id'] : null;

                if ($sesiId && ! in_array($sesiId, $sesiValid, true)) {
                    throw ValidationException::withMessages([
                        'peserta' => 'Ada sesi yang tidak termasuk dalam paket CBT ini.',
                    ]);
                }

                $peserta[$pesertaId]->update([
                    'sesi_ujian_cbt_id' => $sesiId,
                    'status' => $item['status'],
                    'catatan' => filled($item['catatan'] ?? null) ? trim($item['catatan']) : null,
                ]);
            }
        });

        return redirect()
            ->route('ujian-cbt.peserta.index', $ujianCbt)
            ->with('berhasil', 'Peserta CBT berhasil diperbarui.');
    }

    private function pastikanDapatGenerate(UjianCbt $ujianCbt): void
    {
        if ($ujianCbt->status === 'nonaktif') {
            throw ValidationException::withMessages([
                'ujian' => 'Peserta tidak dapat dibuat untuk paket CBT yang nonaktif.',
            ]);
        }

        if (! $ujianCbt->kelasUjianCbt()->exists()) {
            throw ValidationException::withMessages([
                'ujian' => 'Tambahkan minimal satu kelas peserta sebelum membuat peserta CBT.',
            ]);
        }
    }

    private function pastikanAdaSesi(UjianCbt $ujianCbt)
    {
        if (! $ujianCbt->sesiUjianCbt()->exists()) {
            $ujianCbt->sesiUjianCbt()->create([
                'kode' => 'S-01',
                'nama' => 'Sesi 1',
                'waktu_mulai' => $ujianCbt->tanggal_mulai,
                'waktu_selesai' => $ujianCbt->tanggal_selesai,
                'status' => 'draft',
            ]);
        }

        return $ujianCbt->sesiUjianCbt()
            ->withCount('pesertaUjianCbt')
            ->whereIn('status', ['draft', 'aktif'])
            ->orderByRaw('waktu_mulai IS NULL')
            ->orderBy('waktu_mulai')
            ->orderBy('kode')
            ->get();
    }

    private function pilihSesi($sesiUjianCbt, $pemakaianSesi): ?SesiUjianCbt
    {
        if ($sesiUjianCbt->isEmpty()) {
            return null;
        }

        return $sesiUjianCbt
            ->filter(fn ($sesi) => ! $sesi->kapasitas || ($pemakaianSesi[$sesi->id] ?? 0) < $sesi->kapasitas)
            ->sortBy(fn ($sesi) => sprintf(
                '%05d|%s',
                $pemakaianSesi[$sesi->id] ?? 0,
                $sesi->kode,
            ))
            ->first();
    }

    private function buatNomorPeserta(UjianCbt $ujianCbt, KelasUjianCbt $kelasUjianCbt, AnggotaKelas $anggota, int $urutan): string
    {
        $kodeUjian = $this->rapikanKode($ujianCbt->kode);
        $kodeKelas = $this->rapikanKode($kelasUjianCbt->kelas?->nama ?: 'KELAS');
        $nomor = $anggota->nomor_absen ?: $urutan;
        $basis = substr("{$kodeUjian}-{$kodeKelas}-".str_pad((string) $nomor, 3, '0', STR_PAD_LEFT), 0, 74);
        $hasil = $basis;
        $suffix = 2;

        while (PesertaUjianCbt::where('nomor_peserta', $hasil)->exists()) {
            $hasil = substr($basis, 0, 70).'-'.$suffix;
            $suffix++;
        }

        return $hasil;
    }

    private function rapikanKode(string $kode): string
    {
        $hasil = preg_replace('/[^A-Za-z0-9]+/', '', $kode) ?: 'CBT';

        return mb_strtoupper($hasil);
    }
}
