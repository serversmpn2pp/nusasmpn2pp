<?php

namespace App\Http\Controllers;

use App\Models\JawabanPesertaUjianCbt;
use App\Models\PesertaUjianCbt;
use App\Models\SoalUjianCbt;
use App\Models\UjianCbt;
use App\Services\Cbt\JawabanBerkasUjianCbtService;
use App\Services\Cbt\KeamananUjianService;
use App\Services\Cbt\KelayakanPenyelesaianUjianCbtService;
use App\Services\Cbt\KoreksiOtomatisCbtService;
use App\Services\Cbt\PengacakPenyajianCbt;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AksesUjianCbtController extends Controller
{
    public function __construct(
        private readonly PengacakPenyajianCbt $pengacakPenyajianCbt,
        private readonly JawabanBerkasUjianCbtService $jawabanBerkas,
        private readonly KelayakanPenyelesaianUjianCbtService $kelayakanPenyelesaian,
    ) {}

    public function masukDariAkunSiswa(Request $request, PesertaUjianCbt $pesertaUjianCbt)
    {
        $pengguna = $request->user();

        abort_unless($pengguna?->akunSiswa(), 403);

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
            $tokenUjian = mb_strtoupper(trim((string) $peserta->tokenUjianAktif()));

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

        if (! in_array($peserta->status, ['sedang_mengerjakan', 'terblokir'], true)) {
            return redirect()->route('cbt.ujian.show');
        }

        $sisaDetik = $this->hitungSisaDetik($peserta);

        if ($sisaDetik <= 0) {
            $peserta->update($this->dataPenyelesaian($peserta, [
                'status' => 'selesai',
                'waktu_selesai' => now(),
                'menit_tersisa' => 0,
            ]));
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
        $kelayakanSelesai = $this->kelayakanPenyelesaian->ringkasan($peserta, $soalUjian, $sisaDetik);

        return view('cbt.kerjakan', compact('peserta', 'soalUjian', 'jawabanTersimpan', 'pilihanJawaban', 'sisaDetik', 'kelayakanSelesai'));
    }

    public function aktivitasKeamanan(Request $request, KeamananUjianService $service): JsonResponse
    {
        $peserta = $this->ambilPesertaDariSesi($request, true);
        abort_unless(
            in_array($peserta->status, ['sedang_mengerjakan', 'terblokir'], true),
            409,
            'Ujian tidak sedang dikerjakan.',
        );
        $data = $request->validate([
            'peristiwa' => ['required', 'in:keluar,kembali,heartbeat'],
            'metadata' => ['nullable', 'array:visibility,pemicu,fullscreen,online,waktu_klien'],
            'metadata.visibility' => ['nullable', 'string', 'max:20'],
            'metadata.pemicu' => ['nullable', 'string', 'max:30'],
            'metadata.fullscreen' => ['nullable', 'boolean'],
            'metadata.online' => ['nullable', 'boolean'],
            'metadata.waktu_klien' => ['nullable', 'string', 'max:40'],
        ]);
        $userAgent = trim((string) $request->userAgent());
        $perangkat = $userAgent === '' ? 'Web' : 'Web - '.Str::limit($userAgent, 112, '');

        return response()->json([
            'data' => $service->catat(
                $request->user(),
                $peserta,
                $data['peristiwa'],
                $perangkat,
                $request->ip(),
                array_merge($data['metadata'] ?? [], ['saluran' => 'web']),
            ),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function simpan(Request $request, KoreksiOtomatisCbtService $koreksiOtomatisCbtService)
    {
        $peserta = $this->ambilPesertaDariSesi($request);
        $peserta->load('ujianCbt');

        $penyelesaianTerblokirKarenaWaktuHabis = $peserta->status === 'terblokir'
            && $request->input('aksi') === 'selesai'
            && $this->hitungSisaDetik($peserta) <= 0;

        if ($peserta->status !== 'sedang_mengerjakan' && ! $penyelesaianTerblokirKarenaWaktuHabis) {
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

        $inginSelesai = ($data['aksi'] ?? 'simpan') === 'selesai';
        $bolehSelesai = false;

        DB::transaction(function () use ($peserta, $soalUjian, $jawaban, $ragu, $inginSelesai, &$bolehSelesai) {
            $peserta = PesertaUjianCbt::query()->lockForUpdate()->findOrFail($peserta->id);
            $penyelesaianTerblokirKarenaWaktuHabis = $peserta->status === 'terblokir'
                && $inginSelesai
                && $this->hitungSisaDetik($peserta) <= 0;

            if ($peserta->status !== 'sedang_mengerjakan' && ! $penyelesaianTerblokirKarenaWaktuHabis) {
                return;
            }
            foreach ($penyelesaianTerblokirKarenaWaktuHabis ? [] : $soalUjian as $relasiSoal) {
                if ($relasiSoal->soalCbt?->jenis_soal === 'upload_file') {
                    $jawabanBerkas = JawabanPesertaUjianCbt::query()->firstOrNew([
                        'peserta_ujian_cbt_id' => $peserta->id,
                        'soal_ujian_cbt_id' => $relasiSoal->id,
                    ]);
                    $jawabanBerkas->soal_cbt_id = $relasiSoal->soal_cbt_id;
                    $jawabanBerkas->ragu = in_array((int) $relasiSoal->id, $ragu, true);
                    $jawabanBerkas->save();

                    continue;
                }

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

            if ($inginSelesai) {
                $ringkasan = $this->kelayakanPenyelesaian->ringkasan(
                    $peserta,
                    $soalUjian,
                    $this->hitungSisaDetik($peserta),
                );
                $bolehSelesai = $ringkasan['boleh_selesai'];

                if (! $bolehSelesai) {
                    return;
                }

                $peserta->update($this->dataPenyelesaian($peserta, [
                    'status' => 'selesai',
                    'waktu_selesai' => now(),
                    'menit_tersisa' => max(0, (int) ceil($this->hitungSisaDetik($peserta) / 60)),
                ]));
            }
        });

        if ($inginSelesai && ! $bolehSelesai) {
            return redirect()
                ->route('cbt.ujian.kerjakan')
                ->withErrors(['ujian' => $this->kelayakanPenyelesaian->pesanPenolakan()]);
        }

        if ($inginSelesai) {
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
            $peserta->update($this->dataPenyelesaian($peserta, [
                'status' => 'selesai',
                'waktu_selesai' => now(),
                'menit_tersisa' => 0,
            ]));
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
            ->with('soalCbt')
            ->whereKey((int) $data['soal_ujian_cbt_id'])
            ->first();

        abort_unless($relasiSoal, 404);

        $jawaban = DB::transaction(function () use ($peserta, $relasiSoal, $data) {
            $terkunci = PesertaUjianCbt::query()->lockForUpdate()->findOrFail($peserta->id);
            if ($terkunci->status !== 'sedang_mengerjakan') {
                throw new HttpResponseException(response()->json(['message' => 'Ujian tidak sedang dikerjakan.', 'ujian_selesai' => $terkunci->status === 'selesai'], 409));
            }
            $jawabanLama = JawabanPesertaUjianCbt::query()
                ->where('peserta_ujian_cbt_id', $peserta->id)
                ->where('soal_ujian_cbt_id', $relasiSoal->id)
                ->first();
            $nilaiJawaban = $relasiSoal->soalCbt?->jenis_soal === 'upload_file'
                ? ($jawabanLama?->lokasi_file ? ['berkas' => $jawabanLama->nama_file_asli] : null)
                : $this->normalisasiJawaban($data['jawaban'] ?? null);

            return JawabanPesertaUjianCbt::updateOrCreate(
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

        });

        return response()->json([
            'message' => 'Jawaban tersimpan.',
            'terjawab' => $jawaban->jawaban !== null,
            'ragu' => $jawaban->ragu,
            'tersimpan_pada' => now()->format('H:i:s'),
        ]);
    }

    public function simpanBerkasJawaban(Request $request)
    {
        $peserta = $this->ambilPesertaDariSesi($request);
        $peserta->load('ujianCbt');

        abort_unless($peserta->status === 'sedang_mengerjakan', 409, 'Ujian tidak sedang dikerjakan.');
        abort_if($this->hitungSisaDetik($peserta) <= 0, 409, 'Waktu ujian telah berakhir.');

        $data = $request->validate([
            'soal_ujian_cbt_id' => ['required', 'integer'],
            'berkas' => [
                'required',
                'file',
                'mimes:'.implode(',', JawabanBerkasUjianCbtService::EKSTENSI),
                'max:'.JawabanBerkasUjianCbtService::MAKSIMAL_KILOBYTE,
            ],
            'ragu' => ['nullable', 'boolean'],
        ], [
            'berkas.required' => 'Pilih berkas jawaban terlebih dahulu.',
            'berkas.mimes' => 'Format berkas belum didukung. Gunakan PDF, gambar, atau dokumen Office.',
            'berkas.max' => 'Ukuran berkas maksimal 10 MB.',
        ]);
        $soalUjian = $this->ambilSoalUjian($peserta->ujianCbt, $peserta)
            ->firstWhere('id', (int) $data['soal_ujian_cbt_id']);

        abort_unless($soalUjian, 404);

        $jawaban = $this->jawabanBerkas->simpan(
            $peserta,
            $soalUjian,
            $data['berkas'],
            (bool) ($data['ragu'] ?? false),
        );

        return response()->json([
            'message' => 'Berkas jawaban berhasil diunggah.',
            'terjawab' => true,
            'ragu' => (bool) $jawaban->ragu,
            'berkas' => $this->jawabanBerkas->metadata($jawaban),
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

        return redirect()->route('ujian-saya.index', $request->input('tujuan') === 'riwayat'
            ? ['tab' => 'riwayat']
            : []);
    }

    private function ambilPesertaDariSesi(Request $request, bool $responsJson = false): PesertaUjianCbt
    {
        $pesertaId = $request->session()->get('cbt_peserta_ujian_id');

        if (! $pesertaId) {
            if ($responsJson) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Sesi ujian tidak aktif.',
                ], 409));
            }

            throw new HttpResponseException(redirect()->route('ujian-saya.index'));
        }

        $peserta = PesertaUjianCbt::find($pesertaId);

        if (! $peserta) {
            $this->hapusSesiPeserta($request);

            if ($responsJson) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Sesi ujian tidak lagi tersedia.',
                ], 409));
            }

            throw new HttpResponseException(redirect()->route('ujian-saya.index'));
        }

        $pengguna = $request->user();
        $penggunaSesiId = (int) $request->session()->get('cbt_pengguna_id');
        $milikSiswaLogin = $pengguna
            && (int) $pengguna->id === $penggunaSesiId
            && $pengguna->akunSiswa()
            && $peserta->anggotaKelas()
                ->where('siswa_id', $pengguna->siswa_id)
                ->exists();

        if (! $milikSiswaLogin) {
            $this->hapusSesiPeserta($request);

            if ($responsJson) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Sesi ujian tidak sesuai dengan akun siswa yang sedang digunakan.',
                ], 409));
            }

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

        $susulanAktif = $peserta->susulanDijadwalkan();
        $statusPaketDiizinkan = $susulanAktif
            ? ['terjadwal', 'berlangsung', 'selesai']
            : ['terjadwal', 'berlangsung'];

        if (! in_array($ujian->status, $statusPaketDiizinkan, true)) {
            throw ValidationException::withMessages([
                'token' => 'Paket ujian belum dibuka.',
            ]);
        }

        $mulai = $susulanAktif
            ? $peserta->susulan_mulai
            : ($peserta->sesiUjianCbt?->waktu_mulai ?: $ujian->tanggal_mulai);
        $selesai = $susulanAktif
            ? $peserta->susulan_selesai
            : ($peserta->sesiUjianCbt?->waktu_selesai ?: $ujian->tanggal_selesai);

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

        if (! $susulanAktif && $peserta->sesiUjianCbt && $peserta->sesiUjianCbt->status === 'nonaktif') {
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
        $batasPaket = $peserta->susulanDijadwalkan()
            ? $peserta->susulan_selesai
            : ($peserta->sesiUjianCbt?->waktu_selesai ?: $peserta->ujianCbt->tanggal_selesai);

        if ($batasPaket && $batasPaket->lt($selesaiPengerjaan)) {
            $selesaiPengerjaan = $batasPaket;
        }

        return (int) max(0, now()->diffInSeconds($selesaiPengerjaan, false));
    }

    private function dataPenyelesaian(PesertaUjianCbt $peserta, array $data): array
    {
        if ($peserta->susulanDijadwalkan()) {
            $data['status_susulan'] = 'selesai';
        }

        return $data;
    }
}
