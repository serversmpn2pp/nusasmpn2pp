<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KenaikanKelasController extends Controller
{
    public function index(Request $request)
    {
        $tahunPelajaran = TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('nama')
            ->get();
        $tahunAsalId = $request->input('tahun_asal_id', $tahunPelajaran->firstWhere('aktif', true)?->id);
        $tahunTujuanId = $request->input('tahun_tujuan_id');
        $kelasAsalId = $request->input('kelas_asal_id');

        $tahunAsal = $tahunAsalId ? TahunPelajaran::find($tahunAsalId) : null;
        $tahunTujuan = $tahunTujuanId ? TahunPelajaran::find($tahunTujuanId) : null;
        $kelasAsalPilihan = $tahunAsal
            ? Kelas::where('tahun_pelajaran_id', $tahunAsal->id)
                ->withCount('anggotaKelas')
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get()
            : collect();
        $kelasAsal = $kelasAsalId
            ? Kelas::where('tahun_pelajaran_id', $tahunAsal?->id)->find($kelasAsalId)
            : null;
        $kelasTujuan = $tahunTujuan
            ? Kelas::where('tahun_pelajaran_id', $tahunTujuan->id)
                ->where('aktif', true)
                ->withCount('anggotaKelas')
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get()
            : collect();
        $anggotaKelas = collect();
        $anggotaTujuan = collect();
        $saranKelasId = null;

        if ($kelasAsal && $tahunTujuan) {
            $anggotaKelas = $kelasAsal->anggotaKelas()
                ->with('siswa')
                ->orderBy('nomor_absen')
                ->orderBy('id')
                ->get();
            $anggotaTujuan = AnggotaKelas::query()
                ->where('tahun_pelajaran_id', $tahunTujuan->id)
                ->whereIn('siswa_id', $anggotaKelas->pluck('siswa_id'))
                ->with('kelas')
                ->get()
                ->keyBy('siswa_id');
            $saranKelasId = $this->ambilSaranKelasTujuan($kelasAsal, $kelasTujuan)?->id;
        }

        return view('kenaikan-kelas.index', compact(
            'tahunPelajaran',
            'tahunAsal',
            'tahunTujuan',
            'tahunAsalId',
            'tahunTujuanId',
            'kelasAsalId',
            'kelasAsalPilihan',
            'kelasAsal',
            'kelasTujuan',
            'anggotaKelas',
            'anggotaTujuan',
            'saranKelasId',
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tahun_asal_id' => 'required|exists:tahun_pelajaran,id|different:tahun_tujuan_id',
            'tahun_tujuan_id' => 'required|exists:tahun_pelajaran,id',
            'kelas_asal_id' => 'required|exists:kelas,id',
            'tujuan' => 'array',
            'tujuan.*' => 'nullable|exists:kelas,id',
            'nomor_absen' => 'array',
            'nomor_absen.*' => 'nullable|integer|min:1|max:500',
            'keterangan' => 'array',
            'keterangan.*' => 'nullable|string',
        ]);

        $kelasAsal = Kelas::where('tahun_pelajaran_id', $data['tahun_asal_id'])
            ->findOrFail($data['kelas_asal_id']);
        $tahunTujuan = TahunPelajaran::findOrFail($data['tahun_tujuan_id']);
        $kelasTujuan = Kelas::where('tahun_pelajaran_id', $tahunTujuan->id)
            ->where('aktif', true)
            ->get()
            ->keyBy('id');
        $anggotaAsal = $kelasAsal->anggotaKelas()->with('siswa')->get()->keyBy('id');
        $ringkasan = [
            'diproses' => 0,
            'ditempatkan' => 0,
            'dilewati' => 0,
            'catatan' => [],
        ];

        DB::transaction(function () use ($data, $kelasTujuan, $anggotaAsal, $tahunTujuan, &$ringkasan) {
            foreach ($data['tujuan'] ?? [] as $anggotaKelasId => $kelasTujuanId) {
                $anggotaLama = $anggotaAsal->get((int) $anggotaKelasId);

                if (! $anggotaLama) {
                    continue;
                }

                $ringkasan['diproses']++;

                if (! $kelasTujuanId) {
                    $ringkasan['dilewati']++;
                    $ringkasan['catatan'][] = $anggotaLama->siswa?->nama_lengkap . ': belum ditempatkan.';
                    continue;
                }

                $kelasBaru = $kelasTujuan->get((int) $kelasTujuanId);

                if (! $kelasBaru) {
                    $ringkasan['dilewati']++;
                    $ringkasan['catatan'][] = $anggotaLama->siswa?->nama_lengkap . ': kelas tujuan tidak valid.';
                    continue;
                }

                $anggotaTujuan = AnggotaKelas::where('tahun_pelajaran_id', $tahunTujuan->id)
                    ->where('siswa_id', $anggotaLama->siswa_id)
                    ->first();

                if ($this->kelasTujuanPenuh($kelasBaru, $anggotaTujuan)) {
                    $ringkasan['dilewati']++;
                    $ringkasan['catatan'][] = $anggotaLama->siswa?->nama_lengkap . ': kelas ' . $kelasBaru->nama . ' sudah penuh.';
                    continue;
                }

                $nomorAbsen = $data['nomor_absen'][$anggotaKelasId] ?? null;
                $nomorAbsen = $nomorAbsen !== null && $nomorAbsen !== '' ? (int) $nomorAbsen : null;

                if ($nomorAbsen && $this->nomorAbsenTerpakai($kelasBaru, $nomorAbsen, $anggotaTujuan)) {
                    $ringkasan['dilewati']++;
                    $ringkasan['catatan'][] = $anggotaLama->siswa?->nama_lengkap . ': nomor absen ' . $nomorAbsen . ' sudah dipakai di ' . $kelasBaru->nama . '.';
                    continue;
                }

                $nomorAbsen ??= $this->ambilNomorAbsenBerikutnya($kelasBaru, $anggotaTujuan);
                $payload = [
                    'tahun_pelajaran_id' => $tahunTujuan->id,
                    'kelas_id' => $kelasBaru->id,
                    'siswa_id' => $anggotaLama->siswa_id,
                    'nomor_absen' => $nomorAbsen,
                    'status_keanggotaan' => 'aktif',
                    'tanggal_masuk' => $tahunTujuan->tanggal_mulai,
                    'keterangan' => $data['keterangan'][$anggotaKelasId] ?? 'Penempatan massal',
                ];

                if ($anggotaTujuan) {
                    $anggotaTujuan->update($payload);
                } else {
                    AnggotaKelas::create($payload);
                }

                $ringkasan['ditempatkan']++;
            }
        });

        return redirect()
            ->route('kenaikan-kelas.index', [
                'tahun_asal_id' => $data['tahun_asal_id'],
                'tahun_tujuan_id' => $data['tahun_tujuan_id'],
                'kelas_asal_id' => $data['kelas_asal_id'],
            ])
            ->with('berhasil', 'Proses penempatan kelas selesai.')
            ->with('ringkasan_kenaikan', $ringkasan);
    }

    private function ambilSaranKelasTujuan(Kelas $kelasAsal, Collection $kelasTujuan): ?Kelas
    {
        $tingkatTujuan = $kelasAsal->tingkat && $kelasAsal->tingkat < 9
            ? $kelasAsal->tingkat + 1
            : $kelasAsal->tingkat;
        $rombel = $this->ambilRombel($kelasAsal->nama);

        return $kelasTujuan->first(function (Kelas $kelas) use ($tingkatTujuan, $rombel) {
            return (int) $kelas->tingkat === (int) $tingkatTujuan
                && $this->ambilRombel($kelas->nama) === $rombel;
        }) ?? $kelasTujuan->firstWhere('tingkat', $tingkatTujuan);
    }

    private function ambilRombel(string $namaKelas): string
    {
        $namaKelas = mb_strtoupper(trim($namaKelas));

        if (preg_match('/([A-Z])$/', $namaKelas, $cocok)) {
            return $cocok[1];
        }

        return '';
    }

    private function kelasTujuanPenuh(Kelas $kelas, ?AnggotaKelas $anggotaTujuan): bool
    {
        if (! $kelas->kapasitas || ($anggotaTujuan && $anggotaTujuan->kelas_id === $kelas->id)) {
            return false;
        }

        return $kelas->anggotaKelas()->count() >= $kelas->kapasitas;
    }

    private function nomorAbsenTerpakai(Kelas $kelas, int $nomorAbsen, ?AnggotaKelas $anggotaTujuan): bool
    {
        return $kelas->anggotaKelas()
            ->where('nomor_absen', $nomorAbsen)
            ->when($anggotaTujuan, function ($query, $anggotaTujuan) {
                $query->whereKeyNot($anggotaTujuan->id);
            })
            ->exists();
    }

    private function ambilNomorAbsenBerikutnya(Kelas $kelas, ?AnggotaKelas $anggotaTujuan): ?int
    {
        $nomorTerpakai = $kelas->anggotaKelas()
            ->when($anggotaTujuan, function ($query, $anggotaTujuan) {
                $query->whereKeyNot($anggotaTujuan->id);
            })
            ->whereNotNull('nomor_absen')
            ->pluck('nomor_absen')
            ->mapWithKeys(fn ($nomor) => [(int) $nomor => true]);
        $batas = $kelas->kapasitas ?: max(($nomorTerpakai->keys()->max() ?? 0) + 1, 500);

        for ($nomor = 1; $nomor <= $batas; $nomor++) {
            if (! $nomorTerpakai->has($nomor)) {
                return $nomor;
            }
        }

        return null;
    }
}
