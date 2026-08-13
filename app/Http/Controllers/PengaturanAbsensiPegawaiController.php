<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\PengaturanAbsensiPegawai;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PengaturanAbsensiPegawaiController extends Controller
{
    public function index(Request $request)
    {
        $hari = $request->input('hari', 'semua_hari');
        $cakupan = $request->input('cakupan', 'semua_cakupan');
        $status = $request->input('status', 'semua');
        $kataKunci = trim((string) $request->input('q', ''));

        if (! array_key_exists($hari, PengaturanAbsensiPegawai::DAFTAR_HARI) && $hari !== 'semua_hari') {
            $hari = 'semua_hari';
        }

        if (! array_key_exists($cakupan, PengaturanAbsensiPegawai::DAFTAR_CAKUPAN) && $cakupan !== 'semua_cakupan') {
            $cakupan = 'semua_cakupan';
        }

        if (! in_array($status, ['semua', 'aktif', 'nonaktif'], true)) {
            $status = 'semua';
        }

        $pengaturanAbsensiPegawai = PengaturanAbsensiPegawai::query()
            ->with('pegawai:id,nama_lengkap,nip,jenis_pegawai,jabatan_utama')
            ->when($hari !== 'semua_hari', function ($query) use ($hari) {
                $query->where('hari', $hari);
            })
            ->when($cakupan !== 'semua_cakupan', function ($query) use ($cakupan) {
                $query->where('cakupan', $cakupan);
            })
            ->when($status === 'aktif', function ($query) {
                $query->where('aktif', true);
            })
            ->when($status === 'nonaktif', function ($query) {
                $query->where('aktif', false);
            })
            ->when($kataKunci !== '', function ($query) use ($kataKunci) {
                $query->where(function ($subQuery) use ($kataKunci) {
                    $subQuery
                        ->where('nama_jadwal', 'like', "%{$kataKunci}%")
                        ->orWhere('jenis_pegawai', 'like', "%{$kataKunci}%")
                        ->orWhereHas('pegawai', function ($pegawaiQuery) use ($kataKunci) {
                            $pegawaiQuery
                                ->where('nama_lengkap', 'like', "%{$kataKunci}%")
                                ->orWhere('nip', 'like', "%{$kataKunci}%");
                        });
                });
            })
            ->orderBy('urutan_hari')
            ->orderBy('cakupan')
            ->orderBy('nama_jadwal')
            ->paginate(10)
            ->withQueryString();

        $jumlahPengaturanAbsensiPegawai = PengaturanAbsensiPegawai::count();
        $jumlahAktif = PengaturanAbsensiPegawai::where('aktif', true)->count();
        $jumlahNonaktif = PengaturanAbsensiPegawai::where('aktif', false)->count();

        return view('pengaturan-absensi-pegawai.index', compact(
            'pengaturanAbsensiPegawai',
            'hari',
            'cakupan',
            'status',
            'kataKunci',
            'jumlahPengaturanAbsensiPegawai',
            'jumlahAktif',
            'jumlahNonaktif',
        ));
    }

    public function create()
    {
        return view('pengaturan-absensi-pegawai.create', [
            'jenisPegawaiOptions' => $this->pilihanJenisPegawai(),
            'pegawaiOptions' => $this->pilihanPegawai(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->rapikanData($request->validate($this->aturanValidasi()));
        $data['aktif'] = $request->boolean('aktif');
        $this->pastikanUrutanWaktuBenar($data);
        $this->pastikanSasaranTidakDuplikat($data);

        $pengaturanAbsensiPegawai = PengaturanAbsensiPegawai::create($data);

        return redirect()
            ->route('pengaturan-absensi-pegawai.show', $pengaturanAbsensiPegawai)
            ->with('berhasil', 'Pengaturan presensi pegawai berhasil ditambahkan.');
    }

    public function show(PengaturanAbsensiPegawai $pengaturanAbsensiPegawai)
    {
        $pengaturanAbsensiPegawai->load('pegawai:id,nama_lengkap,nip,jenis_pegawai,jabatan_utama');

        return view('pengaturan-absensi-pegawai.show', compact('pengaturanAbsensiPegawai'));
    }

    public function edit(PengaturanAbsensiPegawai $pengaturanAbsensiPegawai)
    {
        return view('pengaturan-absensi-pegawai.edit', [
            'pengaturanAbsensiPegawai' => $pengaturanAbsensiPegawai,
            'jenisPegawaiOptions' => $this->pilihanJenisPegawai(),
            'pegawaiOptions' => $this->pilihanPegawai(),
        ]);
    }

    public function update(Request $request, PengaturanAbsensiPegawai $pengaturanAbsensiPegawai)
    {
        $data = $this->rapikanData($request->validate($this->aturanValidasi()));
        $data['aktif'] = $request->boolean('aktif');
        $this->pastikanUrutanWaktuBenar($data);
        $this->pastikanSasaranTidakDuplikat($data, $pengaturanAbsensiPegawai);

        $pengaturanAbsensiPegawai->update($data);

        return redirect()
            ->route('pengaturan-absensi-pegawai.show', $pengaturanAbsensiPegawai)
            ->with('berhasil', 'Pengaturan presensi pegawai berhasil diperbarui.');
    }

    public function destroy(PengaturanAbsensiPegawai $pengaturanAbsensiPegawai)
    {
        $pengaturanAbsensiPegawai->update(['aktif' => false]);

        return redirect()
            ->route('pengaturan-absensi-pegawai.index')
            ->with('berhasil', 'Pengaturan presensi pegawai berhasil dinonaktifkan.');
    }

    private function aturanValidasi(): array
    {
        return [
            'nama_jadwal' => ['required', 'string', 'max:120'],
            'cakupan' => ['required', Rule::in(array_keys(PengaturanAbsensiPegawai::DAFTAR_CAKUPAN))],
            'jenis_pegawai' => ['nullable', 'required_if:cakupan,jenis_pegawai', 'string', 'max:100'],
            'pegawai_id' => ['nullable', 'required_if:cakupan,pegawai', 'integer', Rule::exists('pegawai', 'id')],
            'hari' => ['required', Rule::in(array_keys(PengaturanAbsensiPegawai::DAFTAR_HARI))],
            'jam_scan_masuk_mulai' => ['required', 'date_format:H:i'],
            'jam_masuk' => ['required', 'date_format:H:i'],
            'jam_scan_masuk_selesai' => ['required', 'date_format:H:i'],
            'jam_scan_pulang_mulai' => ['required', 'date_format:H:i'],
            'jam_pulang' => ['required', 'date_format:H:i'],
            'jam_scan_pulang_selesai' => ['required', 'date_format:H:i'],
            'aktif' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    private function rapikanData(array $data): array
    {
        $data['urutan_hari'] = PengaturanAbsensiPegawai::DAFTAR_HARI[$data['hari']]['urutan'];

        if ($data['cakupan'] !== 'jenis_pegawai') {
            $data['jenis_pegawai'] = null;
        }

        if ($data['cakupan'] !== 'pegawai') {
            $data['pegawai_id'] = null;
        }

        return $data;
    }

    private function pastikanSasaranTidakDuplikat(
        array $data,
        ?PengaturanAbsensiPegawai $pengaturanAbsensiPegawai = null
    ): void {
        $query = PengaturanAbsensiPegawai::query()
            ->where('hari', $data['hari'])
            ->where('cakupan', $data['cakupan']);

        if ($pengaturanAbsensiPegawai) {
            $query->whereKeyNot($pengaturanAbsensiPegawai->getKey());
        }

        if ($data['cakupan'] === 'jenis_pegawai') {
            $query->where('jenis_pegawai', $data['jenis_pegawai']);
        }

        if ($data['cakupan'] === 'pegawai') {
            $query->where('pegawai_id', $data['pegawai_id']);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'hari' => 'Jadwal untuk sasaran tersebut pada hari yang sama sudah ada. Silakan edit jadwal yang sudah tersedia.',
            ]);
        }
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
                'jam_masuk' => 'Jam masuk resmi harus berada di antara mulai scan dan tutup scan masuk.',
            ]);
        }

        if ($mulaiPulang > $jamPulang || $jamPulang > $selesaiPulang) {
            throw ValidationException::withMessages([
                'jam_pulang' => 'Jam pulang resmi harus berada di antara mulai scan dan tutup scan pulang.',
            ]);
        }
    }

    private function menit(string $jam): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $jam));

        return ($hour * 60) + $minute;
    }

    private function pilihanJenisPegawai()
    {
        return Pegawai::query()
            ->whereNotNull('jenis_pegawai')
            ->where('jenis_pegawai', '!=', '')
            ->select('jenis_pegawai')
            ->distinct()
            ->orderBy('jenis_pegawai')
            ->pluck('jenis_pegawai');
    }

    private function pilihanPegawai()
    {
        return Pegawai::query()
            ->where('aktif', true)
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nip', 'jenis_pegawai', 'jabatan_utama']);
    }
}
