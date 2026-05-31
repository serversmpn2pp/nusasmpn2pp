<?php

namespace App\Http\Controllers;

use App\Models\JamPelajaran;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JamPelajaranController extends Controller
{
    public function index(Request $request)
    {
        $hari = $request->input('hari', 'semua');
        $status = $request->input('status', 'semua');

        if (! array_key_exists($hari, JamPelajaran::DAFTAR_HARI) && $hari !== 'semua') {
            $hari = 'semua';
        }

        if (! in_array($status, ['semua', 'aktif', 'nonaktif'], true)) {
            $status = 'semua';
        }

        $jamPelajaran = JamPelajaran::query()
            ->when($hari !== 'semua', fn ($query) => $query->where('hari', $hari))
            ->when($status === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('aktif', false))
            ->orderByRaw("case hari when 'senin' then 1 when 'selasa' then 2 when 'rabu' then 3 when 'kamis' then 4 when 'jumat' then 5 when 'sabtu' then 6 else 7 end")
            ->orderBy('nomor_jam')
            ->paginate(15)
            ->withQueryString();

        return view('jam-pelajaran.index', [
            'jamPelajaran' => $jamPelajaran,
            'hari' => $hari,
            'status' => $status,
            'daftarHari' => JamPelajaran::DAFTAR_HARI,
            'jumlahJamPelajaran' => JamPelajaran::count(),
            'jumlahAktif' => JamPelajaran::where('aktif', true)->count(),
            'jumlahNonaktif' => JamPelajaran::where('aktif', false)->count(),
        ]);
    }

    public function create()
    {
        return view('jam-pelajaran.create', [
            'daftarHari' => JamPelajaran::DAFTAR_HARI,
            'daftarJenis' => JamPelajaran::DAFTAR_JENIS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->aturanValidasi());
        $data['aktif'] = $request->boolean('aktif');
        $this->pastikanJamValid($data);

        $jamPelajaran = JamPelajaran::create($data);

        return redirect()
            ->route('jam-pelajaran.show', $jamPelajaran)
            ->with('berhasil', 'Jam pelajaran berhasil ditambahkan.');
    }

    public function show(JamPelajaran $jamPelajaran)
    {
        $jamPelajaran->loadCount('jadwalPelajaran');

        return view('jam-pelajaran.show', compact('jamPelajaran'));
    }

    public function edit(JamPelajaran $jamPelajaran)
    {
        return view('jam-pelajaran.edit', [
            'jamPelajaran' => $jamPelajaran,
            'daftarHari' => JamPelajaran::DAFTAR_HARI,
            'daftarJenis' => JamPelajaran::DAFTAR_JENIS,
        ]);
    }

    public function update(Request $request, JamPelajaran $jamPelajaran)
    {
        $data = $request->validate($this->aturanValidasi($jamPelajaran));
        $data['aktif'] = $request->boolean('aktif');
        $this->pastikanJamValid($data);

        $jamPelajaran->update($data);

        return redirect()
            ->route('jam-pelajaran.show', $jamPelajaran)
            ->with('berhasil', 'Jam pelajaran berhasil diperbarui.');
    }

    public function destroy(JamPelajaran $jamPelajaran)
    {
        $jamPelajaran->update(['aktif' => false]);

        return redirect()
            ->route('jam-pelajaran.index')
            ->with('berhasil', 'Jam pelajaran berhasil dinonaktifkan.');
    }

    private function aturanValidasi(?JamPelajaran $jamPelajaran = null): array
    {
        return [
            'hari' => ['required', Rule::in(array_keys(JamPelajaran::DAFTAR_HARI))],
            'nomor_jam' => [
                'required',
                'integer',
                'min:1',
                'max:20',
                Rule::unique('jam_pelajaran', 'nomor_jam')
                    ->where('hari', request('hari'))
                    ->ignore($jamPelajaran),
            ],
            'label' => ['nullable', 'string', 'max:100'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i'],
            'jenis' => ['required', Rule::in(array_keys(JamPelajaran::DAFTAR_JENIS))],
            'aktif' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    private function pastikanJamValid(array $data): void
    {
        if ($this->menitDariJam($data['jam_selesai']) <= $this->menitDariJam($data['jam_mulai'])) {
            throw ValidationException::withMessages([
                'jam_selesai' => 'Jam selesai harus lebih besar dari jam mulai.',
            ]);
        }
    }

    private function menitDariJam(string $jam): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($jam, 0, 5)));

        return ($hour * 60) + $minute;
    }
}
