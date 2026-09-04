<?php

namespace App\Http\Controllers;

use App\Models\PengaturanAbsensi;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PengaturanAbsensiController extends Controller
{
    public function index(Request $request)
    {
        $hari = $request->input('hari', 'semua');
        $status = $request->input('status', 'semua');

        if (! array_key_exists($hari, PengaturanAbsensi::DAFTAR_HARI) && $hari !== 'semua') {
            $hari = 'semua';
        }

        if (! in_array($status, ['semua', 'aktif', 'nonaktif'], true)) {
            $status = 'semua';
        }

        $pengaturanAbsensi = PengaturanAbsensi::query()
            ->when($hari !== 'semua', function ($query) use ($hari) {
                $query->where('hari', $hari);
            })
            ->when($status === 'aktif', function ($query) {
                $query->where('aktif', true);
            })
            ->when($status === 'nonaktif', function ($query) {
                $query->where('aktif', false);
            })
            ->orderBy('urutan_hari')
            ->orderBy('hari')
            ->paginate(10)
            ->withQueryString();

        $jumlahPengaturanAbsensi = PengaturanAbsensi::count();
        $jumlahAktif = PengaturanAbsensi::where('aktif', true)->count();
        $jumlahNonaktif = PengaturanAbsensi::where('aktif', false)->count();

        return view('pengaturan-absensi.index', compact(
            'pengaturanAbsensi',
            'hari',
            'status',
            'jumlahPengaturanAbsensi',
            'jumlahAktif',
            'jumlahNonaktif',
        ));
    }

    public function create()
    {
        return view('pengaturan-absensi.create');
    }

    public function store(Request $request)
    {
        $data = $this->rapikanData($request->validate($this->aturanValidasi()));
        $data['aktif'] = $request->boolean('aktif');
        $this->pastikanUrutanWaktuBenar($data);

        $pengaturanAbsensi = PengaturanAbsensi::create($data);

        return redirect()
            ->route('pengaturan-absensi.show', $pengaturanAbsensi)
            ->with('berhasil', 'Pengaturan presensi berhasil ditambahkan.');
    }

    public function show(PengaturanAbsensi $pengaturanAbsensi)
    {
        return view('pengaturan-absensi.show', compact('pengaturanAbsensi'));
    }

    public function edit(PengaturanAbsensi $pengaturanAbsensi)
    {
        return view('pengaturan-absensi.edit', compact('pengaturanAbsensi'));
    }

    public function update(Request $request, PengaturanAbsensi $pengaturanAbsensi)
    {
        $data = $this->rapikanData($request->validate($this->aturanValidasi($pengaturanAbsensi)));
        $data['aktif'] = $request->boolean('aktif');
        $this->pastikanUrutanWaktuBenar($data);

        $pengaturanAbsensi->update($data);

        return redirect()
            ->route('pengaturan-absensi.show', $pengaturanAbsensi)
            ->with('berhasil', 'Pengaturan presensi berhasil diperbarui.');
    }

    public function destroy(PengaturanAbsensi $pengaturanAbsensi)
    {
        $pengaturanAbsensi->update(['aktif' => false]);

        return redirect()
            ->route('pengaturan-absensi.index')
            ->with('berhasil', 'Pengaturan presensi berhasil dinonaktifkan.');
    }

    private function aturanValidasi(?PengaturanAbsensi $pengaturanAbsensi = null): array
    {
        return [
            'hari' => [
                'required',
                Rule::in(array_keys(PengaturanAbsensi::DAFTAR_HARI)),
                Rule::unique('pengaturan_absensi', 'hari')->ignore($pengaturanAbsensi),
            ],
            'jam_scan_masuk_mulai' => ['required', 'date_format:H:i'],
            'jam_masuk' => ['required', 'date_format:H:i'],
            'jam_scan_masuk_selesai' => ['required', 'date_format:H:i'],
            'jam_scan_pulang_mulai' => ['required', 'date_format:H:i'],
            'jam_pulang' => ['required', 'date_format:H:i'],
            'jam_scan_pulang_selesai' => ['required', 'date_format:H:i'],
            'pulang_jumat_dibedakan' => ['nullable', 'boolean'],
            'jam_scan_pulang_perempuan_mulai' => ['nullable', 'date_format:H:i'],
            'jam_pulang_perempuan' => ['nullable', 'date_format:H:i'],
            'jam_scan_pulang_perempuan_selesai' => ['nullable', 'date_format:H:i'],
            'aktif' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    private function rapikanData(array $data): array
    {
        $data['urutan_hari'] = PengaturanAbsensi::DAFTAR_HARI[$data['hari']]['urutan'];
        $data['pulang_jumat_dibedakan'] = $data['hari'] === 'jumat'
            && (bool) ($data['pulang_jumat_dibedakan'] ?? false);

        if (! $data['pulang_jumat_dibedakan']) {
            $data['jam_scan_pulang_perempuan_mulai'] = null;
            $data['jam_pulang_perempuan'] = null;
            $data['jam_scan_pulang_perempuan_selesai'] = null;
        }

        return $data;
    }

    private function pastikanUrutanWaktuBenar(array $data): void
    {
        $mulaiMasuk = $this->menit($data['jam_scan_masuk_mulai']);
        $jamMasuk = $this->menit($data['jam_masuk']);
        $selesaiMasuk = $this->menit($data['jam_scan_masuk_selesai']);
        $mulaiPulang = $this->menit($data['jam_scan_pulang_mulai']);
        $jamPulang = $this->menit($data['jam_pulang']);
        $selesaiPulang = $this->menit($data['jam_scan_pulang_selesai']);

        if ($mulaiMasuk > $jamMasuk || $jamMasuk > $selesaiMasuk) {
            throw ValidationException::withMessages([
                'jam_masuk' => 'Jam masuk harus berada di antara jam mulai scan masuk dan tutup scan masuk.',
            ]);
        }

        if ($mulaiPulang > $jamPulang || $jamPulang > $selesaiPulang) {
            throw ValidationException::withMessages([
                'jam_pulang' => 'Jam pulang harus berada di antara jam mulai scan pulang dan tutup scan pulang.',
            ]);
        }

        if (! $data['pulang_jumat_dibedakan']) {
            return;
        }

        $kolomPerempuan = [
            'jam_scan_pulang_perempuan_mulai' => 'Waktu mulai scan pulang siswi wajib diisi.',
            'jam_pulang_perempuan' => 'Jam pulang resmi siswi wajib diisi.',
            'jam_scan_pulang_perempuan_selesai' => 'Waktu tutup scan pulang siswi wajib diisi.',
        ];
        $pesan = [];

        foreach ($kolomPerempuan as $kolom => $message) {
            if (blank($data[$kolom] ?? null)) {
                $pesan[$kolom] = $message;
            }
        }

        if ($pesan !== []) {
            throw ValidationException::withMessages($pesan);
        }

        $mulaiPerempuan = $this->menit($data['jam_scan_pulang_perempuan_mulai']);
        $jamPerempuan = $this->menit($data['jam_pulang_perempuan']);
        $selesaiPerempuan = $this->menit($data['jam_scan_pulang_perempuan_selesai']);

        if ($mulaiPerempuan > $jamPerempuan || $jamPerempuan > $selesaiPerempuan) {
            throw ValidationException::withMessages([
                'jam_pulang_perempuan' => 'Jam pulang resmi siswi harus berada di antara waktu mulai dan tutup scan.',
            ]);
        }

        if ($mulaiPerempuan > $mulaiPulang || $jamPerempuan > $jamPulang) {
            throw ValidationException::withMessages([
                'jam_pulang_perempuan' => 'Jadwal pulang siswi harus sama atau lebih awal daripada jadwal siswa laki-laki.',
            ]);
        }
    }

    private function menit(string $jam): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $jam));

        return ($hour * 60) + $minute;
    }
}
