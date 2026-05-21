<?php

namespace App\Http\Controllers;

use App\Models\AbsensiPegawai;
use App\Models\Pegawai;
use App\Models\PengaturanAbsensiPegawai;
use App\Services\Absensi\ProsesScanAbsensiPegawai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ScanAbsensiPegawaiController extends Controller
{
    public function index()
    {
        $hari = $this->hariDariTanggal(now()->isoWeekday());
        $jadwalHariIni = PengaturanAbsensiPegawai::query()
            ->with('pegawai:id,nama_lengkap,nip,jenis_pegawai,jabatan_utama')
            ->where('hari', $hari)
            ->where('aktif', true)
            ->orderBy('jam_scan_masuk_mulai')
            ->orderBy('cakupan')
            ->orderBy('nama_jadwal')
            ->get();

        return view('scan-absensi-pegawai.index', [
            'hariLabel' => PengaturanAbsensiPegawai::DAFTAR_HARI[$hari]['label'] ?? ucfirst($hari),
            'tanggalHariIni' => now()->locale('id')->translatedFormat('d F Y'),
            'jadwalHariIni' => $jadwalHariIni,
            'jadwalJson' => $jadwalHariIni->map(fn (PengaturanAbsensiPegawai $jadwal) => $this->jadwalUntukHalaman($jadwal))->values(),
        ]);
    }

    public function store(Request $request, ProsesScanAbsensiPegawai $prosesScanAbsensiPegawai): JsonResponse
    {
        $data = $request->validate([
            'isi_scan' => ['required', 'string', 'max:100'],
            'jenis_scan' => ['nullable', Rule::in(['masuk', 'pulang'])],
        ]);

        $hasil = $prosesScanAbsensiPegawai->proses(
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
            'nip' => $hasil['nip'] ?? null,
            'waktu_server' => now()->format('H:i:s'),
            'pegawai' => $this->dataPegawai($hasil['pegawai'] ?? null),
            'absensi' => $this->dataAbsensi($hasil['absensi'] ?? null),
            'jadwal' => $this->dataJadwal($hasil['jadwal'] ?? null),
        ], $hasil['berhasil'] ? 200 : 422);
    }

    private function jadwalUntukHalaman(PengaturanAbsensiPegawai $jadwal): array
    {
        return [
            'id' => $jadwal->id,
            'nama_jadwal' => $jadwal->nama_jadwal,
            'cakupan' => $jadwal->labelCakupan(),
            'sasaran' => $jadwal->labelSasaran(),
            'jam_scan_masuk_mulai' => $jadwal->formatJam($jadwal->jam_scan_masuk_mulai),
            'jam_masuk' => $jadwal->formatJam($jadwal->jam_masuk),
            'jam_scan_masuk_selesai' => $jadwal->formatJam($jadwal->jam_scan_masuk_selesai),
            'jam_scan_pulang_mulai' => $jadwal->formatJam($jadwal->jam_scan_pulang_mulai),
            'jam_pulang' => $jadwal->formatJam($jadwal->jam_pulang),
            'jam_scan_pulang_selesai' => $jadwal->formatJam($jadwal->jam_scan_pulang_selesai),
        ];
    }

    private function dataPegawai(?Pegawai $pegawai): ?array
    {
        if (! $pegawai) {
            return null;
        }

        return [
            'nama_lengkap' => $pegawai->nama_lengkap,
            'nip' => $pegawai->nip,
            'jenis_pegawai' => $pegawai->jenis_pegawai,
            'jabatan' => $pegawai->jabatan_utama ?: $pegawai->jenis_pegawai,
            'foto_url' => $pegawai->foto && Storage::disk('public')->exists($pegawai->foto)
                ? asset('storage/' . $pegawai->foto)
                : null,
            'inisial' => $this->inisial($pegawai->nama_lengkap),
        ];
    }

    private function dataAbsensi(?AbsensiPegawai $absensi): ?array
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

    private function dataJadwal(?PengaturanAbsensiPegawai $jadwal): ?array
    {
        if (! $jadwal) {
            return null;
        }

        return [
            'nama_jadwal' => $jadwal->nama_jadwal,
            'sasaran' => $jadwal->labelSasaran(),
            'jam_masuk' => $jadwal->formatJam($jadwal->jam_masuk),
            'jam_pulang' => $jadwal->formatJam($jadwal->jam_pulang),
        ];
    }

    private function formatJam(?string $jam): ?string
    {
        return $jam ? substr($jam, 0, 5) : null;
    }

    private function inisial(string $nama): string
    {
        $kata = preg_split('/\s+/', trim($nama)) ?: [];
        $inisial = '';

        foreach (array_slice($kata, 0, 2) as $bagian) {
            $inisial .= mb_substr($bagian, 0, 1);
        }

        return mb_strtoupper($inisial ?: 'P');
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
