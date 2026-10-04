<?php

namespace App\Services\Humas;

use App\Models\TahunPelajaran;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PeriodeDashboardHumasService
{
    public function periode(Request $request): array
    {
        $tahun = TahunPelajaran::orderByDesc('tanggal_mulai')->orderByDesc('id')->get();
        $data = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'periode' => ['nullable', Rule::in(['tahunan', 'ganjil', 'genap', 'kustom'])],
            'tanggal_mulai' => ['required_if:periode,kustom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before:2101-01-01'],
            'tanggal_selesai' => ['required_if:periode,kustom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before:2101-01-01'],
        ]);
        $pilihan = isset($data['tahun_pelajaran_id'])
            ? $tahun->firstWhere('id', (int) $data['tahun_pelajaran_id']) : $tahun->firstWhere('aktif', true);
        $periode = $data['periode'] ?? ($pilihan ? 'tahunan' : 'kustom');

        if ($periode === 'kustom') {
            $mulai = CarbonImmutable::parse($data['tanggal_mulai'] ?? today()->subDays(29)->toDateString())->startOfDay();
            $selesai = CarbonImmutable::parse($data['tanggal_selesai'] ?? today()->toDateString())->startOfDay();
            $pilihan = null;
        } else {
            if (! $pilihan) {
                throw ValidationException::withMessages(['tahun_pelajaran_id' => 'Pilih tahun pelajaran untuk periode semester atau tahunan.']);
            }
            $mulai = CarbonImmutable::parse($pilihan->tanggal_mulai)->startOfDay();
            $selesai = CarbonImmutable::parse($pilihan->tanggal_selesai)->startOfDay();
            $batasSemester = $mulai->startOfYear()->addYear();
            if ($periode === 'ganjil') {
                $selesai = $selesai->min($batasSemester->subDay());
            } elseif ($periode === 'genap') {
                $mulai = $mulai->max($batasSemester);
            }
        }
        if ($selesai->lt($mulai) || $selesai->gte($mulai->addMonthsNoOverflow(24))) {
            throw ValidationException::withMessages(['tanggal_selesai' => 'Periode harus berurutan dan paling lama 24 bulan. Periksa juga tanggal tahun pelajaran.']);
        }
        $filter = ['tahun_pelajaran_id' => $pilihan?->id, 'periode' => $periode,
            'tanggal_mulai' => $mulai->toDateString(), 'tanggal_selesai' => $selesai->toDateString()];

        return [
            'tahun' => $tahun, 'filter' => $filter, 'mulai' => $mulai, 'selesai' => $selesai,
            'labelPeriode' => $periode === 'kustom' ? 'Rentang tanggal' : $pilihan->nama.' / '.['tahunan' => 'Satu tahun', 'ganjil' => 'Semester ganjil', 'genap' => 'Semester genap'][$periode],
        ];
    }
}
