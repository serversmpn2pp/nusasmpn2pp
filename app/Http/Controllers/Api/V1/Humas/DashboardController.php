<?php

namespace App\Http\Controllers\Api\V1\Humas;

use App\Models\TahunPelajaran;
use App\Services\Humas\DashboardHumasService;
use App\Services\Humas\PeriodeDashboardHumasService;
use Illuminate\Http\Request;

class DashboardController extends HumasController
{
    public function index(Request $request, PeriodeDashboardHumasService $periode, DashboardHumasService $dashboard)
    {
        $this->staff($request, 'dashboard_humas.lihat');
        $p = $periode->periode($request);
        $k = $dashboard->ringkasan($request->user(), $p['mulai'], $p['selesai'], $p['filter']['tahun_pelajaran_id']);

        return $this->json([
            'filter' => $p['filter'], 'label_periode' => $p['labelPeriode'],
            'metrik' => (object) collect($k['metrik'])->map(fn ($m) => array_diff_key($m, ['url' => true]))->all(),
            'perhatian' => collect($k['perhatian'])->map(fn ($m) => array_diff_key($m, ['url' => true]))->values(),
            'distribusi' => (object) $k['distribusi'], 'kolom_bulanan' => (object) $k['kolomBulanan'],
            'bulan' => collect($k['bulan'])->map(fn ($b) => ['label' => $b['label'], 'jumlah' => (object) $b['jumlah']]),
            'jumlah_program' => $k['jumlahProgram'],
            'program' => $k['program']->map(fn ($p) => $p->only(['id', 'nama', 'status']) + [
                'target_kegiatan' => (int) $p->target_kegiatan, 'tanggal_selesai' => $p->tanggal_selesai?->format('Y-m-d'),
                'realisasi_periode' => (int) $p->realisasi_periode,
            ]),
            'agenda' => $k['agenda']->map(fn ($a) => $a->only(['id', 'judul', 'jenis', 'tempat']) + [
                'waktu_mulai' => $a->waktu_mulai->toIso8601String(), 'waktu_selesai' => $a->waktu_selesai->toIso8601String(),
            ]),
        ]);
    }

    public function referensi(Request $request)
    {
        $this->staff($request, 'dashboard_humas.lihat');

        return $this->json(['tahun' => TahunPelajaran::orderByDesc('tanggal_mulai')->orderByDesc('id')->limit(100)
            ->get(['id', 'nama', 'aktif', 'tanggal_mulai', 'tanggal_selesai'])->map(fn ($t) => $t->only(['id', 'nama', 'aktif']) + [
                'tanggal_mulai' => $t->tanggal_mulai?->format('Y-m-d'), 'tanggal_selesai' => $t->tanggal_selesai?->format('Y-m-d'),
            ]),
            'periode' => ['tahunan' => 'Satu tahun', 'ganjil' => 'Semester ganjil', 'genap' => 'Semester genap', 'kustom' => 'Rentang tanggal'],
        ]);
    }
}
