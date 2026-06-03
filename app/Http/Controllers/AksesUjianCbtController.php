<?php

namespace App\Http\Controllers;

use App\Models\AkunPesertaCbt;
use App\Models\JawabanPesertaUjianCbt;
use App\Models\PesertaUjianCbt;
use App\Models\SoalUjianCbt;
use App\Models\UjianCbt;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AksesUjianCbtController extends Controller
{
    public function createLogin()
    {
        return view('cbt.login');
    }

    public function storeLogin(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:80'],
            'kata_sandi' => ['required', 'string', 'max:40'],
            'token' => ['required', 'string', 'max:20'],
        ]);

        $token = mb_strtoupper(trim($data['token']));
        $ujianCbt = UjianCbt::query()
            ->with(['jenisUjianCbt', 'tahunPelajaran', 'mataPelajaran'])
            ->where('token', $token)
            ->whereIn('status', ['terjadwal', 'berlangsung'])
            ->first();

        if (! $ujianCbt) {
            throw ValidationException::withMessages([
                'token' => 'Token ujian tidak valid atau ujian belum dibuka.',
            ]);
        }

        $akunPeserta = AkunPesertaCbt::query()
            ->with('anggotaKelas.siswa')
            ->where('jenis_ujian_cbt_id', $ujianCbt->jenis_ujian_cbt_id)
            ->where('tahun_pelajaran_id', $ujianCbt->tahun_pelajaran_id)
            ->where('semester', $ujianCbt->semester)
            ->where('username', trim($data['username']))
            ->where('kata_sandi', trim($data['kata_sandi']))
            ->where('status', 'aktif')
            ->first();

        if (! $akunPeserta) {
            throw ValidationException::withMessages([
                'username' => 'Username atau password CBT tidak sesuai.',
            ]);
        }

        $peserta = PesertaUjianCbt::query()
            ->with(['ujianCbt', 'sesiUjianCbt', 'kelasUjianCbt.kelas', 'anggotaKelas.siswa'])
            ->where('ujian_cbt_id', $ujianCbt->id)
            ->where(function ($query) use ($akunPeserta) {
                $query->where('akun_peserta_cbt_id', $akunPeserta->id)
                    ->orWhere('anggota_kelas_id', $akunPeserta->anggota_kelas_id);
            })
            ->first();

        if (! $peserta) {
            throw ValidationException::withMessages([
                'username' => 'Akun ini tidak terdaftar sebagai peserta paket ujian tersebut.',
            ]);
        }

        $this->pastikanPesertaBolehMasuk($peserta);

        if (! $peserta->akun_peserta_cbt_id) {
            $peserta->update(['akun_peserta_cbt_id' => $akunPeserta->id]);
        }

        $peserta->update([
            'ip_terakhir' => $request->ip(),
            'user_agent_terakhir' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        $request->session()->put('cbt_peserta_ujian_id', $peserta->id);
        $request->session()->regenerate();

        return redirect()->route('cbt.ujian.show');
    }

    public function show(Request $request)
    {
        $peserta = $this->ambilPesertaDariSesi($request);
        $peserta->load([
            'akunPesertaCbt',
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

    public function kerjakan(Request $request)
    {
        $peserta = $this->ambilPesertaDariSesi($request);
        $peserta->load([
            'akunPesertaCbt',
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

            return redirect()->route('cbt.ujian.selesai');
        }

        $soalUjian = $this->ambilSoalUjian($peserta->ujianCbt);
        $jawabanTersimpan = $peserta->jawabanPesertaUjianCbt()
            ->whereIn('soal_ujian_cbt_id', $soalUjian->pluck('id'))
            ->get()
            ->keyBy('soal_ujian_cbt_id');

        return view('cbt.kerjakan', compact('peserta', 'soalUjian', 'jawabanTersimpan', 'sisaDetik'));
    }

    public function simpan(Request $request)
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

        $soalUjian = $this->ambilSoalUjian($peserta->ujianCbt);
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
            return redirect()->route('cbt.ujian.selesai');
        }

        return redirect()
            ->route('cbt.ujian.kerjakan')
            ->with('berhasil', 'Jawaban berhasil disimpan.');
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
        $request->session()->forget('cbt_peserta_ujian_id');

        return redirect()->route('cbt.login');
    }

    private function ambilPesertaDariSesi(Request $request): PesertaUjianCbt
    {
        $pesertaId = $request->session()->get('cbt_peserta_ujian_id');

        if (! $pesertaId) {
            throw new HttpResponseException(redirect()->route('cbt.login'));
        }

        $peserta = PesertaUjianCbt::find($pesertaId);

        if (! $peserta) {
            $request->session()->forget('cbt_peserta_ujian_id');
            throw new HttpResponseException(redirect()->route('cbt.login'));
        }

        return $peserta;
    }

    private function pastikanPesertaBolehMasuk(PesertaUjianCbt $peserta): void
    {
        $peserta->loadMissing(['ujianCbt', 'sesiUjianCbt']);
        $ujian = $peserta->ujianCbt;

        if (! in_array($peserta->status, ['aktif', 'sedang_mengerjakan'], true)) {
            throw ValidationException::withMessages([
                'username' => 'Status peserta tidak aktif untuk mengikuti ujian.',
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

    private function ambilSoalUjian(UjianCbt $ujianCbt)
    {
        return $ujianCbt->soalUjianCbt()
            ->with('soalCbt')
            ->get()
            ->sortBy(fn (SoalUjianCbt $item) => sprintf('%05d|%08d', $item->nomor_urut ?? 9999, $item->id))
            ->values()
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
