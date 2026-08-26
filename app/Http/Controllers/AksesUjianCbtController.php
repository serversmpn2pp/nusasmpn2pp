<?php

namespace App\Http\Controllers;

use App\Models\JawabanPesertaUjianCbt;
use App\Models\PesertaUjianCbt;
use App\Models\SoalUjianCbt;
use App\Models\UjianCbt;
use App\Services\Cbt\KoreksiOtomatisCbtService;
use App\Services\Cbt\PengacakPenyajianCbt;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AksesUjianCbtController extends Controller
{
    public function __construct(private readonly PengacakPenyajianCbt $pengacakPenyajianCbt) {}

    public function masukDariAkunSiswa(Request $request, PesertaUjianCbt $pesertaUjianCbt)
    {
        $pengguna = $request->user();

        abort_unless($pengguna?->akunSiswa() || $pengguna?->memilikiPeran('siswa'), 403);

        $siswa = $pengguna->siswa()->firstOrFail();
        $peserta = PesertaUjianCbt::query()
            ->with([
                'ujianCbt.jenisUjianCbt',
                'sesiUjianCbt',
                'kelasUjianCbt.kelas',
                'anggotaKelas.siswa',
            ])
            ->whereKey($pesertaUjianCbt->id)
            ->whereHas('anggotaKelas', fn ($query) => $query->where('siswa_id', $siswa->id))
            ->firstOrFail();

        $data = $request->validate([
            'token' => ['nullable', 'string', 'max:20'],
        ]);
        $perluToken = (bool) $peserta->ujianCbt?->jenisUjianCbt?->memerlukan_token
            && $peserta->status !== 'sedang_mengerjakan';

        if ($perluToken) {
            $tokenDimasukkan = mb_strtoupper(trim((string) ($data['token'] ?? '')));
            $tokenUjian = mb_strtoupper(trim((string) $peserta->ujianCbt?->token));

            if ($tokenDimasukkan === '' || $tokenUjian === '' || ! hash_equals($tokenUjian, $tokenDimasukkan)) {
                throw ValidationException::withMessages([
                    'token' => 'Token ujian tidak valid. Silakan minta token yang sedang berlaku kepada pengawas.',
                ]);
            }
        }

        $this->pastikanPesertaBolehMasuk($peserta);
        $this->aktifkanSesiPeserta($request, $peserta, $pengguna->id);

        return redirect()->route('cbt.ujian.show');
    }

    public function show(Request $request)
    {
        $peserta = $this->ambilPesertaDariSesi($request);
        $peserta->load([
            'ujianCbt.jenisUjianCbt',
            'ujianCbt.tahunPelajaran',
            'ujianCbt.mataPelajaran',
            'sesiUjianCbt',
            'kelasUjianCbt.kelas',
            'anggotaKelas.siswa',
        ]);

        $jumlahSoal = $peserta->ujianCbt->soalUjianCbt()->count();
        $jumlahJawaban = $peserta->jawabanPesertaUjianCbt()->whereNotNull('jawaban')->count();

        return view('cbt.show', compact('peserta', 'jumlahSoal', 'jumlahJawaban'));
    }

    public function mulai(Request $request)
    {
        $peserta = $this->ambilPesertaDariSesi($request);
        $peserta->load(['ujianCbt', 'sesiUjianCbt']);

        $this->pastikanPesertaBolehMasuk($peserta);

        if (! $peserta->ujianCbt->soalUjianCbt()->exists()) {
            throw ValidationException::withMessages([
                'ujian' => 'Paket ujian belum memiliki soal.',
            ]);
        }

        if ($peserta->status === 'aktif') {
            $peserta->update([
                'status' => 'sedang_mengerjakan',
                'waktu_mulai' => now(),
                'menit_tersisa' => $peserta->ujianCbt->durasi_menit,
            ]);
        }

        return redirect()->route('cbt.ujian.kerjakan');
    }

    public function kerjakan(Request $request, KoreksiOtomatisCbtService $koreksiOtomatisCbtService)
    {
        $peserta = $this->ambilPesertaDariSesi($request);
        $peserta->load([
            'ujianCbt.mataPelajaran',
            'sesiUjianCbt',
            'kelasUjianCbt.kelas',
            'anggotaKelas.siswa',
        ]);

        if ($peserta->status === 'selesai') {
            return redirect()->route('cbt.ujian.selesai');
        }

        if ($peserta->status !== 'sedang_mengerjakan') {
            return redirect()->route('cbt.ujian.show');
        }

        $sisaDetik = $this->hitungSisaDetik($peserta);

        if ($sisaDetik <= 0) {
            $peserta->update([
                'status' => 'selesai',
                'waktu_selesai' => now(),
                'menit_tersisa' => 0,
            ]);
            $peserta->refresh();
            $koreksiOtomatisCbtService->koreksiPeserta($peserta);

            return redirect()->route('cbt.ujian.selesai');
        }

        $soalUjian = $this->ambilSoalUjian($peserta->ujianCbt, $peserta);
        $jawabanTersimpan = $peserta->jawabanPesertaUjianCbt()
            ->whereIn('soal_ujian_cbt_id', $soalUjian->pluck('id'))
            ->get()
            ->keyBy('soal_ujian_cbt_id');
        $pilihanJawaban = $soalUjian->mapWithKeys(fn (SoalUjianCbt $relasiSoal) => [
            $relasiSoal->id => $this->pengacakPenyajianCbt->pilihanJawaban(
                $peserta->ujianCbt,
                $peserta,
                $relasiSoal,
            ),
        ]);

        return view('cbt.kerjakan', compact('peserta', 'soalUjian', 'jawabanTersimpan', 'pilihanJawaban', 'sisaDetik'));
    }

    public function simpan(Request $request, KoreksiOtomatisCbtService $koreksiOtomatisCbtService)
    {
        $peserta = $this->ambilPesertaDariSesi($request);
        $peserta->load('ujianCbt');

        if ($peserta->status !== 'sedang_mengerjakan') {
            return redirect()->route('cbt.ujian.show');
        }

        $data = $request->validate([
            'jawaban' => ['nullable', 'array'],
            'ragu' => ['nullable', 'array'],
            'aksi' => ['nullable', 'in:simpan,selesai'],
        ]);

        $soalUjian = $this->ambilSoalUjian($peserta->ujianCbt, $peserta);
        $jawaban = $data['jawaban'] ?? [];
        $ragu = collect($data['ragu'] ?? [])
            ->filter(fn ($nilai) => filter_var($nilai, FILTER_VALIDATE_BOOLEAN))
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->all();

        DB::transaction(function () use ($peserta, $soalUjian, $jawaban, $ragu, $data) {
            foreach ($soalUjian as $relasiSoal) {
                $nilaiJawaban = $this->normalisasiJawaban($jawaban[$relasiSoal->id] ?? null);

                JawabanPesertaUjianCbt::updateOrCreate(
                    [
                        'peserta_ujian_cbt_id' => $peserta->id,
                        'soal_ujian_cbt_id' => $relasiSoal->id,
                    ],
                    [
                        'soal_cbt_id' => $relasiSoal->soal_cbt_id,
                        'jawaban' => $nilaiJawaban,
                        'ragu' => in_array((int) $relasiSoal->id, $ragu, true),
                        'skor' => null,
                        'benar' => null,
                        'waktu_dijawab' => $nilaiJawaban === null ? null : now(),
                    ],
                );
            }

            if (($data['aksi'] ?? 'simpan') === 'selesai') {
                $peserta->update([
                    'status' => 'selesai',
                    'waktu_selesai' => now(),
                    'menit_tersisa' => max(0, (int) ceil($this->hitungSisaDetik($peserta) / 60)),
                ]);
            }
        });

        if (($data['aksi'] ?? 'simpan') === 'selesai') {
            $peserta->refresh();
            $koreksiOtomatisCbtService->koreksiPeserta($peserta);

            return redirect()->route('cbt.ujian.selesai');
        }

        return redirect()
            ->route('cbt.ujian.kerjakan')
            ->with('berhasil', 'Jawaban berhasil disimpan.');
    }

    public function simpanJawaban(Request $request, KoreksiOtomatisCbtService $koreksiOtomatisCbtService)
    {
        $peserta = $this->ambilPesertaDariSesi($request);
        $peserta->load('ujianCbt');

        if ($peserta->status !== 'sedang_mengerjakan') {
            return response()->json([
                'message' => 'Ujian tidak sedang dikerjakan.',
                'ujian_selesai' => $peserta->status === 'selesai',
            ], 409);
        }

        if ($this->hitungSisaDetik($peserta) <= 0) {
            $peserta->update([
                'status' => 'selesai',
                'waktu_selesai' => now(),
                'menit_tersisa' => 0,
            ]);
            $peserta->refresh();
            $koreksiOtomatisCbtService->koreksiPeserta($peserta);

            return response()->json([
                'message' => 'Waktu ujian telah berakhir.',
                'ujian_selesai' => true,
            ], 409);
        }

        $data = $request->validate([
            'soal_ujian_cbt_id' => ['required', 'integer'],
            'jawaban' => ['nullable', 'array'],
            'ragu' => ['nullable', 'boolean'],
        ]);
        $relasiSoal = $peserta->ujianCbt->soalUjianCbt()
            ->whereKey((int) $data['soal_ujian_cbt_id'])
            ->first();

        abort_unless($relasiSoal, 404);

        $nilaiJawaban = $this->normalisasiJawaban($data['jawaban'] ?? null);
        $jawaban = JawabanPesertaUjianCbt::updateOrCreate(
            [
                'peserta_ujian_cbt_id' => $peserta->id,
                'soal_ujian_cbt_id' => $relasiSoal->id,
            ],
            [
                'soal_cbt_id' => $relasiSoal->soal_cbt_id,
                'jawaban' => $nilaiJawaban,
                'ragu' => (bool) ($data['ragu'] ?? false),
                'skor' => null,
                'benar' => null,
                'waktu_dijawab' => $nilaiJawaban === null ? null : now(),
            ],
        );

        return response()->json([
            'message' => 'Jawaban tersimpan.',
            'terjawab' => $jawaban->jawaban !== null,
            'ragu' => $jawaban->ragu,
            'tersimpan_pada' => now()->format('H:i:s'),
        ]);
    }

    public function selesai(Request $request)
    {
        $peserta = $this->ambilPesertaDariSesi($request);
        $peserta->load([
            'ujianCbt.mataPelajaran',
            'kelasUjianCbt.kelas',
            'anggotaKelas.siswa',
        ]);

        $jumlahSoal = $peserta->ujianCbt->soalUjianCbt()->count();
        $jumlahJawaban = $peserta->jawabanPesertaUjianCbt()->whereNotNull('jawaban')->count();

        return view('cbt.selesai', compact('peserta', 'jumlahSoal', 'jumlahJawaban'));
    }

    public function logout(Request $request)
    {
        $this->hapusSesiPeserta($request);

        return redirect()->route('ujian-saya.index');
    }

    private function ambilPesertaDariSesi(Request $request): PesertaUjianCbt
    {
        $pesertaId = $request->session()->get('cbt_peserta_ujian_id');

        if (! $pesertaId) {
            throw new HttpResponseException(redirect()->route('ujian-saya.index'));
        }

        $peserta = PesertaUjianCbt::find($pesertaId);

        if (! $peserta) {
            $this->hapusSesiPeserta($request);
            throw new HttpResponseException(redirect()->route('ujian-saya.index'));
        }

        $pengguna = $request->user();
        $penggunaSesiId = (int) $request->session()->get('cbt_pengguna_id');
        $milikSiswaLogin = $pengguna
            && (int) $pengguna->id === $penggunaSesiId
            && ($pengguna->akunSiswa() || $pengguna->memilikiPeran('siswa'))
            && $peserta->anggotaKelas()
                ->where('siswa_id', $pengguna->siswa_id)
                ->exists();

        if (! $milikSiswaLogin) {
            $this->hapusSesiPeserta($request);

            throw new HttpResponseException(redirect()
                ->route($pengguna ? 'beranda' : 'login')
                ->with('gagal', 'Sesi ujian tidak sesuai dengan akun siswa yang sedang digunakan.'));
        }

        return $peserta;
    }

    private function aktifkanSesiPeserta(
        Request $request,
        PesertaUjianCbt $peserta,
        int $penggunaId,
    ): void {
        $peserta->update([
            'ip_terakhir' => $request->ip(),
            'user_agent_terakhir' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        $request->session()->put([
            'cbt_peserta_ujian_id' => $peserta->id,
            'cbt_pengguna_id' => $penggunaId,
        ]);

        $request->session()->regenerate();
    }

    private function hapusSesiPeserta(Request $request): void
    {
        $request->session()->forget([
            'cbt_peserta_ujian_id',
            'cbt_pengguna_id',
        ]);
    }

    private function pastikanPesertaBolehMasuk(PesertaUjianCbt $peserta): void
    {
        $peserta->loadMissing(['ujianCbt', 'sesiUjianCbt']);
        $ujian = $peserta->ujianCbt;

        if (! in_array($peserta->status, ['aktif', 'sedang_mengerjakan'], true)) {
            throw ValidationException::withMessages([
                'ujian' => 'Status peserta tidak aktif untuk mengikuti ujian.',
            ]);
        }

        if (! in_array($ujian->status, ['terjadwal', 'berlangsung'], true)) {
            throw ValidationException::withMessages([
                'token' => 'Paket ujian belum dibuka.',
            ]);
        }

        $mulai = $peserta->sesiUjianCbt?->waktu_mulai ?: $ujian->tanggal_mulai;
        $selesai = $peserta->sesiUjianCbt?->waktu_selesai ?: $ujian->tanggal_selesai;

        if ($mulai && now()->lt($mulai)) {
            throw ValidationException::withMessages([
                'token' => 'Ujian belum masuk waktu pelaksanaan.',
            ]);
        }

        if ($selesai && now()->gt($selesai)) {
            throw ValidationException::withMessages([
                'token' => 'Waktu pelaksanaan ujian sudah berakhir.',
            ]);
        }

        if ($peserta->sesiUjianCbt && $peserta->sesiUjianCbt->status === 'nonaktif') {
            throw ValidationException::withMessages([
                'token' => 'Sesi peserta tidak aktif.',
            ]);
        }
    }

    private function ambilSoalUjian(UjianCbt $ujianCbt, PesertaUjianCbt $peserta)
    {
        $soal = $ujianCbt->soalUjianCbt()
            ->with('soalCbt')
            ->get();

        return $this->pengacakPenyajianCbt
            ->urutkanSoal($ujianCbt, $peserta, $soal)
            ->take($ujianCbt->jumlah_soal);
    }

    private function normalisasiJawaban(mixed $jawaban): ?array
    {
        if (is_array($jawaban)) {
            $hasil = collect($jawaban)
                ->map(fn ($item) => is_string($item) ? trim($item) : $item)
                ->filter(fn ($item) => filled($item))
                ->all();

            if ($hasil === []) {
                return null;
            }

            return array_is_list($jawaban) ? array_values($hasil) : $hasil;
        }

        if (is_string($jawaban)) {
            $jawaban = trim($jawaban);

            return $jawaban === '' ? null : [$jawaban];
        }

        return filled($jawaban) ? [$jawaban] : null;
    }

    private function hitungSisaDetik(PesertaUjianCbt $peserta): int
    {
        if (! $peserta->waktu_mulai) {
            return $peserta->ujianCbt->durasi_menit * 60;
        }

        $selesaiPengerjaan = $peserta->waktu_mulai->copy()->addMinutes($peserta->ujianCbt->durasi_menit);
        $batasPaket = $peserta->sesiUjianCbt?->waktu_selesai ?: $peserta->ujianCbt->tanggal_selesai;

        if ($batasPaket && $batasPaket->lt($selesaiPengerjaan)) {
            $selesaiPengerjaan = $batasPaket;
        }

        return (int) max(0, now()->diffInSeconds($selesaiPengerjaan, false));
    }
}
