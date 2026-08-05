<?php

namespace App\Http\Controllers;

use App\Models\AbsensiSiswa;
use App\Models\PengaturanAbsensi;
use App\Models\Siswa;
use App\Services\Absensi\ProsesScanAbsensi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ScanAbsensiController extends Controller
{
    public function index()
    {
        $hari = $this->hariDariTanggal(now()->isoWeekday());
        $pengaturanAbsensi = PengaturanAbsensi::query()
            ->where('hari', $hari)
            ->where('aktif', true)
            ->first();

        return view('scan-absensi.index', [
            'hariLabel' => PengaturanAbsensi::DAFTAR_HARI[$hari]['label'] ?? ucfirst($hari),
            'tanggalHariIni' => now()->locale('id')->translatedFormat('d F Y'),
            'pengaturanAbsensi' => $pengaturanAbsensi,
            'jadwal' => $pengaturanAbsensi ? $this->jadwalUntukHalaman($pengaturanAbsensi) : null,
        ]);
    }

    public function store(Request $request, ProsesScanAbsensi $prosesScanAbsensi): JsonResponse
    {
        $data = $request->validate([
            'isi_scan' => ['required', 'string', 'max:100'],
            'jenis_scan' => ['nullable', Rule::in(['masuk', 'pulang'])],
        ]);

        $hasil = $prosesScanAbsensi->proses(
            isiScan: $data['isi_scan'],
            waktuScan: now(),
            jenisScanDiminta: $data['jenis_scan'] ?? null,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json([
            'berhasil' => $hasil['berhasil'],
            'status' => $hasil['status'],
            'pesan' => $hasil['pesan'],
            'jenis_scan' => $hasil['jenis_scan'],
            'scanner_id' => $hasil['scanner_id'] ?? null,
            'nisn' => $hasil['nisn'] ?? null,
            'waktu_server' => now()->format('H:i:s'),
            'siswa' => $this->dataSiswa($hasil['siswa'] ?? null),
            'absensi' => $this->dataAbsensi($hasil['absensi'] ?? null),
        ], $hasil['berhasil'] ? 200 : 422);
    }

    private function jadwalUntukHalaman(PengaturanAbsensi $pengaturanAbsensi): array
    {
        return [
            'hari' => $pengaturanAbsensi->hari,
            'hari_label' => $pengaturanAbsensi->labelHari(),
            'jam_scan_masuk_mulai' => $pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_scan_masuk_mulai),
            'jam_masuk' => $pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_masuk),
            'jam_scan_masuk_selesai' => $pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_scan_masuk_selesai),
            'jam_scan_pulang_mulai' => $pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_scan_pulang_mulai),
            'jam_pulang' => $pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_pulang),
            'jam_scan_pulang_selesai' => $pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_scan_pulang_selesai),
        ];
    }

    private function dataSiswa(?Siswa $siswa): ?array
    {
        if (! $siswa) {
            return null;
        }

        return [
            'nama_lengkap' => $siswa->nama_lengkap,
            'nisn' => $siswa->nisn,
            'foto_url' => $siswa->foto && Storage::disk('public')->exists($siswa->foto)
                ? asset('storage/' . $siswa->foto)
                : null,
            'inisial' => $this->inisial($siswa->nama_lengkap),
        ];
    }

    private function dataAbsensi(?AbsensiSiswa $absensi): ?array
    {
        if (! $absensi) {
            return null;
        }

        return [
            'jam_masuk' => $this->formatJam($absensi->jam_masuk),
            'status_masuk' => $absensi->status_masuk,
            'menit_terlambat' => $absensi->menit_terlambat,
            'jam_pulang' => $this->formatJam($absensi->jam_pulang),
            'status_pulang' => $absensi->status_pulang,
            'menit_pulang_cepat' => $absensi->menit_pulang_cepat,
            'status_kehadiran' => $absensi->status_kehadiran,
        ];
    }

    private function formatJam(?string $jam): ?string
    {
        return $jam ? substr($jam, 0, 8) : null;
    }

    private function inisial(string $nama): string
    {
        $kata = preg_split('/\s+/', trim($nama)) ?: [];
        $inisial = '';

        foreach (array_slice($kata, 0, 2) as $bagian) {
            $inisial .= mb_substr($bagian, 0, 1);
        }

        return mb_strtoupper($inisial ?: 'S');
    }

    private function hariDariTanggal(int $isoWeekday): string
    {
        return [
            1 => 'senin',
            2 => 'selasa',
            3 => 'rabu',
            4 => 'kamis',
            5 => 'jumat',
            6 => 'sabtu',
            7 => 'minggu',
        ][$isoWeekday];
    }
}
