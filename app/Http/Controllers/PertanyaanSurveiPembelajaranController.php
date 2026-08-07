<?php

namespace App\Http\Controllers;

use App\Models\PertanyaanSurveiPembelajaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PertanyaanSurveiPembelajaranController extends Controller
{
    public function index(Request $request)
    {
        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $status = in_array($request->input('status'), ['aktif', 'nonaktif'], true)
            ? $request->input('status')
            : 'semua';

        $pertanyaan = PertanyaanSurveiPembelajaran::query()
            ->when($kataKunci !== '', fn ($query) => $query->where('pernyataan', 'ilike', '%'.$kataKunci.'%'))
            ->when($status === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('aktif', false))
            ->terurut()
            ->paginate(12)
            ->withQueryString();

        return view('pertanyaan-survei-pembelajaran.index', [
            'pertanyaan' => $pertanyaan,
            'kataKunci' => $kataKunci,
            'status' => $status,
            'jumlahPertanyaan' => PertanyaanSurveiPembelajaran::count(),
            'jumlahAktif' => PertanyaanSurveiPembelajaran::aktif()->count(),
            'jumlahNonaktif' => PertanyaanSurveiPembelajaran::where('aktif', false)->count(),
        ]);
    }

    public function create()
    {
        return view('pertanyaan-survei-pembelajaran.create', [
            'urutanBerikutnya' => (int) PertanyaanSurveiPembelajaran::max('urutan') + 1,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validasi($request);
        $data['kode'] = $this->kodeBaru();
        $data['aktif'] = $request->boolean('aktif', true);

        PertanyaanSurveiPembelajaran::create($data);

        return redirect()
            ->route('pertanyaan-survei-pembelajaran.index')
            ->with('berhasil', 'Pernyataan survei berhasil ditambahkan.');
    }

    public function edit(PertanyaanSurveiPembelajaran $pertanyaanSurveiPembelajaran)
    {
        return view('pertanyaan-survei-pembelajaran.edit', compact('pertanyaanSurveiPembelajaran'));
    }

    public function update(Request $request, PertanyaanSurveiPembelajaran $pertanyaanSurveiPembelajaran)
    {
        $pertanyaanSurveiPembelajaran->update($this->validasi($request));

        return redirect()
            ->route('pertanyaan-survei-pembelajaran.index')
            ->with('berhasil', 'Pernyataan survei berhasil diperbarui. Survei lama tetap memakai teks sebelumnya.');
    }

    public function ubahStatus(PertanyaanSurveiPembelajaran $pertanyaanSurveiPembelajaran)
    {
        DB::transaction(function () use ($pertanyaanSurveiPembelajaran): void {
            $pertanyaan = PertanyaanSurveiPembelajaran::query()
                ->lockForUpdate()
                ->findOrFail($pertanyaanSurveiPembelajaran->id);

            if ($pertanyaan->aktif && PertanyaanSurveiPembelajaran::aktif()->count() <= 1) {
                throw ValidationException::withMessages([
                    'status' => 'Minimal satu pernyataan survei harus tetap aktif.',
                ]);
            }

            $pertanyaan->update(['aktif' => ! $pertanyaan->aktif]);
        });

        $status = $pertanyaanSurveiPembelajaran->aktif ? 'dinonaktifkan' : 'diaktifkan';

        return redirect()
            ->route('pertanyaan-survei-pembelajaran.index')
            ->with('berhasil', "Pernyataan survei berhasil {$status}.");
    }

    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'pernyataan' => ['required', 'string', 'max:500'],
            'urutan' => ['required', 'integer', 'min:1', 'max:999'],
        ], [
            'pernyataan.required' => 'Pernyataan survei wajib diisi.',
            'pernyataan.max' => 'Pernyataan survei maksimal 500 karakter.',
            'urutan.required' => 'Urutan wajib diisi.',
            'urutan.integer' => 'Urutan harus berupa angka.',
            'urutan.min' => 'Urutan minimal 1.',
            'urutan.max' => 'Urutan maksimal 999.',
        ]);

        $data['pernyataan'] = trim($data['pernyataan']);
        $data['urutan'] = (int) $data['urutan'];

        return $data;
    }

    private function kodeBaru(): string
    {
        do {
            $kode = 'survei_'.Str::lower(Str::random(12));
        } while (PertanyaanSurveiPembelajaran::where('kode', $kode)->exists());

        return $kode;
    }
}
