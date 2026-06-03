<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\KelasUjianOmr;
use App\Models\LembarJawabUjianOmr;
use App\Models\UjianOmr;
use App\Support\QrCodeNisn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LembarJawabUjianOmrController extends Controller
{
    public function store(Request $request, UjianOmr $ujianOmr)
    {
        $this->pastikanDapatDibuat($ujianOmr);
        $versiSoal = $ujianOmr->versiSoal()
            ->where('aktif', true)
            ->orderBy('kode')
            ->get();
        $kelasPeserta = $ujianOmr->kelasUjianOmr()
            ->with('kelas')
            ->orderBy('kelas_id')
            ->get();
        $jumlahBaru = 0;

        DB::transaction(function () use ($request, $ujianOmr, $versiSoal, $kelasPeserta, &$jumlahBaru) {
            foreach ($kelasPeserta as $kelasUjianOmr) {
                $anggotaKelas = AnggotaKelas::query()
                    ->where('tahun_pelajaran_id', $ujianOmr->tahun_pelajaran_id)
                    ->where('kelas_id', $kelasUjianOmr->kelas_id)
                    ->where('status_keanggotaan', 'aktif')
                    ->whereHas('siswa', fn ($query) => $query->where('aktif', true))
                    ->orderByRaw('nomor_absen IS NULL')
                    ->orderBy('nomor_absen')
                    ->orderBy('id')
                    ->get();

                foreach ($anggotaKelas as $index => $anggota) {
                    $sudahAda = LembarJawabUjianOmr::query()
                        ->where('ujian_omr_id', $ujianOmr->id)
                        ->where('anggota_kelas_id', $anggota->id)
                        ->exists();

                    if ($sudahAda) {
                        continue;
                    }

                    LembarJawabUjianOmr::create([
                        'ujian_omr_id' => $ujianOmr->id,
                        'kelas_ujian_omr_id' => $kelasUjianOmr->id,
                        'anggota_kelas_id' => $anggota->id,
                        'versi_soal_ujian_omr_id' => $versiSoal[$index % $versiSoal->count()]->id,
                        'token' => $this->buatTokenUnik(),
                        'status' => 'siap_dicetak',
                        'dibuat_oleh_pengguna_id' => $request->user()?->id,
                    ]);
                    $jumlahBaru++;
                }
            }
        });

        $jumlahTotal = $ujianOmr->lembarJawabUjianOmr()->count();
        $pesan = $jumlahBaru
            ? "{$jumlahBaru} LJK baru berhasil dibuat. Total {$jumlahTotal} LJK siap dicetak."
            : "Seluruh siswa peserta sudah memiliki LJK. Total {$jumlahTotal} LJK siap dicetak.";

        return redirect()
            ->route('ujian-omr.show', $ujianOmr)
            ->with('berhasil', $pesan);
    }

    public function cetak(Request $request, UjianOmr $ujianOmr)
    {
        $data = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
        ]);
        $kelasId = isset($data['kelas_id']) ? (int) $data['kelas_id'] : null;

        if ($kelasId && ! $ujianOmr->kelasUjianOmr()->where('kelas_id', $kelasId)->exists()) {
            throw ValidationException::withMessages([
                'kelas_id' => 'Kelas yang dipilih bukan peserta ujian ini.',
            ]);
        }

        $ujianOmr->load(['tahunPelajaran', 'mataPelajaran']);
        $lembarJawab = $ujianOmr->lembarJawabUjianOmr()
            ->with([
                'anggotaKelas.kelas',
                'anggotaKelas.siswa',
                'versiSoalUjianOmr',
            ])
            ->when($kelasId, fn ($query) => $query->whereHas(
                'anggotaKelas',
                fn ($query) => $query->where('kelas_id', $kelasId),
            ))
            ->get()
            ->sortBy(fn ($lembar) => sprintf(
                '%s|%05d|%s',
                $lembar->anggotaKelas?->kelas?->nama ?? '',
                $lembar->anggotaKelas?->nomor_absen ?? 999,
                $lembar->anggotaKelas?->siswa?->nama_lengkap ?? '',
            ))
            ->values()
            ->map(fn (LembarJawabUjianOmr $lembar) => [
                'lembar' => $lembar,
                'qr_svg' => QrCodeNisn::svg($lembar->token),
            ]);

        return view('ujian-omr.cetak-lembar-jawab', compact('ujianOmr', 'lembarJawab'));
    }

    private function pastikanDapatDibuat(UjianOmr $ujianOmr): void
    {
        if ($ujianOmr->status !== 'siap') {
            throw ValidationException::withMessages([
                'ujian' => 'LJK hanya dapat dibuat setelah ujian berstatus siap digunakan.',
            ]);
        }

        if (! $ujianOmr->kelasUjianOmr()->exists()) {
            throw ValidationException::withMessages([
                'ujian' => 'Tambahkan minimal satu kelas peserta sebelum membuat LJK.',
            ]);
        }

        if (! $ujianOmr->versiSoal()->where('aktif', true)->exists()) {
            throw ValidationException::withMessages([
                'ujian' => 'Tambahkan minimal satu versi soal aktif sebelum membuat LJK.',
            ]);
        }
    }

    private function buatTokenUnik(): string
    {
        do {
            $token = (string) random_int(1, 9);

            for ($i = 1; $i < 18; $i++) {
                $token .= (string) random_int(0, 9);
            }
        } while (LembarJawabUjianOmr::where('token', $token)->exists());

        return $token;
    }
}
