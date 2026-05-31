<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Support\PembacaExcelSiswa;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SiswaController extends Controller
{
    public function index(Request $request)
    {
        $kata_kunci = $request->kata_kunci;
        $status = $request->input('status', 'semua');
        $cakupanWaliKelas = $request->user()?->membatasiCakupanWaliKelas() ?? false;

        if (! in_array($status, ['semua', 'aktif', 'nonaktif'], true)) {
            $status = 'semua';
        }

        $siswa = $this->querySiswaDalamCakupan($request)
            ->select([
                'id',
                'nama_lengkap',
                'nis',
                'nisn',
                'nik',
                'foto',
                'jenis_kelamin',
                'tempat_lahir',
                'tanggal_lahir',
                'agama',
                'nama_ayah',
                'nama_ibu',
                'aktif',
            ])
            ->when($status === 'aktif', function ($query) {
                $query->where('aktif', true);
            })
            ->when($status === 'nonaktif', function ($query) {
                $query->where('aktif', false);
            })
            ->when($kata_kunci, function ($query, $kata_kunci) {
                $query->where(function ($query) use ($kata_kunci) {
                    $query->where('nama_lengkap', 'ilike', '%' . $kata_kunci . '%')
                        ->orWhere('nis', 'ilike', '%' . $kata_kunci . '%')
                        ->orWhere('nisn', 'ilike', '%' . $kata_kunci . '%')
                        ->orWhere('nik', 'ilike', '%' . $kata_kunci . '%');
                });
            })
            ->orderBy('nama_lengkap')
            ->paginate(10)
            ->withQueryString();

        $jumlahSiswa = $this->querySiswaDalamCakupan($request)->count();
        $jumlahAktif = $this->querySiswaDalamCakupan($request)->where('aktif', true)->count();
        $jumlahNonaktif = $this->querySiswaDalamCakupan($request)->where('aktif', false)->count();

        return view('siswa.index', compact(
            'siswa',
            'kata_kunci',
            'status',
            'jumlahSiswa',
            'jumlahAktif',
            'jumlahNonaktif',
            'cakupanWaliKelas',
        ));
    }

    public function create()
    {
        return view('siswa.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->aturanValidasi());
        $data['aktif'] = $request->boolean('aktif');

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('siswa/foto', 'public');
        }

        Siswa::create($data);

        return redirect()
            ->route('siswa.index')
            ->with('berhasil', 'Data siswa berhasil ditambahkan.');
    }

    public function show(Request $request, Siswa $siswa)
    {
        $this->pastikanBolehAksesSiswa($request, $siswa);

        return view('siswa.show', compact('siswa'));
    }

    public function edit(Request $request, Siswa $siswa)
    {
        $this->pastikanBolehAksesSiswa($request, $siswa);

        return view('siswa.edit', compact('siswa'));
    }

    public function update(Request $request, Siswa $siswa)
    {
        $this->pastikanBolehAksesSiswa($request, $siswa);

        $data = $request->validate($this->aturanValidasi($siswa));
        $data['aktif'] = $request->boolean('aktif');

        if ($request->hasFile('foto')) {
            if ($siswa->foto) {
                Storage::disk('public')->delete($siswa->foto);
            }

            $data['foto'] = $request->file('foto')->store('siswa/foto', 'public');
        } else {
            unset($data['foto']);
        }

        $siswa->update($data);

        return redirect()
            ->route('siswa.index')
            ->with('berhasil', 'Data siswa berhasil diperbarui.');
    }

    public function destroy(Request $request, Siswa $siswa)
    {
        $this->pastikanBolehAksesSiswa($request, $siswa);

        $siswa->update([
            'aktif' => false,
        ]);

        return redirect()
            ->route('siswa.index')
            ->with('berhasil', 'Data siswa berhasil dinonaktifkan.');
    }

    public function createImport()
    {
        $tahunPelajaranAktif = TahunPelajaran::where('aktif', true)->first();
        $jumlahKelasAktif = $tahunPelajaranAktif
            ? Kelas::where('tahun_pelajaran_id', $tahunPelajaranAktif->id)->where('aktif', true)->count()
            : 0;

        return view('siswa.import', compact('tahunPelajaranAktif', 'jumlahKelasAktif'));
    }

    public function storeImport(Request $request, PembacaExcelSiswa $pembacaExcelSiswa)
    {
        $data = $request->validate([
            'berkas_excel' => 'required|file|mimes:xlsx|max:10240',
        ]);

        $barisSiswa = $pembacaExcelSiswa->baca($data['berkas_excel']->getRealPath());

        $ringkasan = [
            'dibaca' => count($barisSiswa),
            'ditambahkan' => 0,
            'diperbarui' => 0,
            'ditempatkan_kelas' => 0,
            'gagal_ditempatkan' => 0,
            'dilewati' => 0,
            'kelas_ditemukan' => [],
            'catatan' => [],
        ];
        $identitasDiBerkas = [
            'nisn' => [],
            'nis' => [],
            'nik' => [],
        ];
        $tahunPelajaranAktif = TahunPelajaran::where('aktif', true)->first();
        $kelasAktif = $tahunPelajaranAktif
            ? Kelas::where('tahun_pelajaran_id', $tahunPelajaranAktif->id)
                ->where('aktif', true)
                ->get()
                ->keyBy(fn (Kelas $kelas) => $this->normalisasiNamaKelas($kelas->nama))
            : collect();

        foreach ($barisSiswa as $baris) {
            $dataSiswa = $baris['data'];
            $duplikatBerkas = $this->cekDuplikatDiBerkas($baris, $identitasDiBerkas);

            if ($duplikatBerkas) {
                $ringkasan['dilewati']++;
                $ringkasan['catatan'][] = $duplikatBerkas;
            } else {
                try {
                    $siswa = $this->cariSiswaUntukImport($dataSiswa);

                    if ($siswa) {
                        $siswa->update($dataSiswa);
                        $ringkasan['diperbarui']++;
                    } else {
                        $siswa = Siswa::create($dataSiswa);
                        $ringkasan['ditambahkan']++;
                    }

                    $this->tempatkanSiswaSaatImport(
                        $siswa,
                        $baris['kelas_saat_import'],
                        $baris['nomor_baris'],
                        $tahunPelajaranAktif,
                        $kelasAktif,
                        $ringkasan,
                    );
                } catch (QueryException $exception) {
                    $ringkasan['dilewati']++;
                    $ringkasan['catatan'][] = 'Baris ' . $baris['nomor_baris'] . ': data tidak disimpan karena bentrok dengan data unik yang sudah ada.';
                }
            }

            if ($baris['kelas_saat_import']) {
                $ringkasan['kelas_ditemukan'][$baris['kelas_saat_import']] = true;
            }
        }

        $ringkasan['kelas_ditemukan'] = array_keys($ringkasan['kelas_ditemukan']);
        $ringkasan['tahun_pelajaran'] = $tahunPelajaranAktif?->nama;

        return redirect()
            ->route('siswa.index')
            ->with('berhasil', 'Import Excel siswa selesai.')
            ->with('ringkasan_import', $ringkasan);
    }

    private function aturanValidasi(?Siswa $siswa = null): array
    {
        return [
            'nama_lengkap' => 'required|string|max:255',
            'nis' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('siswa', 'nis')->ignore($siswa),
            ],
            'nisn' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('siswa', 'nisn')->ignore($siswa),
            ],
            'nik' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('siswa', 'nik')->ignore($siswa),
            ],
            'foto' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'jenis_kelamin' => 'nullable|in:L,P',
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'agama' => 'nullable|string|max:100',
            'status_dalam_keluarga' => 'nullable|string|max:100',
            'anak_ke' => 'nullable|integer|min:1|max:30',
            'nama_ayah' => 'nullable|string|max:255',
            'nomor_wa_ayah' => 'nullable|string|max:30',
            'nama_ibu' => 'nullable|string|max:255',
            'nomor_wa_ibu' => 'nullable|string|max:30',
            'pekerjaan_ayah' => 'nullable|string|max:150',
            'pekerjaan_ibu' => 'nullable|string|max:150',
            'nama_wali' => 'nullable|string|max:255',
            'hubungan_wali' => 'nullable|string|max:100',
            'nomor_wa_wali' => 'nullable|string|max:30',
            'kontak_absensi_utama' => ['nullable', Rule::in(['ayah', 'ibu', 'wali'])],
            'alamat' => 'nullable|string',
            'sekolah_asal' => 'nullable|string|max:255',
            'keterangan' => 'nullable|string',
            'aktif' => 'nullable|boolean',
        ];
    }

    private function querySiswaDalamCakupan(Request $request)
    {
        $query = Siswa::query();
        $pengguna = $request->user();

        if ($pengguna?->membatasiCakupanWaliKelas()) {
            $query->whereHas('anggotaKelas', function ($query) use ($pengguna) {
                $query->whereIn('kelas_id', $pengguna->kelasWaliIds());
            });
        }

        return $query;
    }

    private function pastikanBolehAksesSiswa(Request $request, Siswa $siswa): void
    {
        $pengguna = $request->user();

        if (! $pengguna?->membatasiCakupanWaliKelas()) {
            return;
        }

        abort_unless(
            Siswa::query()
                ->whereKey($siswa->id)
                ->whereHas('anggotaKelas', function ($query) use ($pengguna) {
                    $query->whereIn('kelas_id', $pengguna->kelasWaliIds());
                })
                ->exists(),
            403,
        );
    }

    private function cariSiswaUntukImport(array $dataSiswa): ?Siswa
    {
        if (empty($dataSiswa['nisn']) && empty($dataSiswa['nis']) && empty($dataSiswa['nik'])) {
            return null;
        }

        return Siswa::query()
            ->when($dataSiswa['nisn'] ?? null, function ($query, $nisn) {
                $query->orWhere('nisn', $nisn);
            })
            ->when($dataSiswa['nis'] ?? null, function ($query, $nis) {
                $query->orWhere('nis', $nis);
            })
            ->when($dataSiswa['nik'] ?? null, function ($query, $nik) {
                $query->orWhere('nik', $nik);
            })
            ->first();
    }

    private function tempatkanSiswaSaatImport(
        Siswa $siswa,
        ?string $namaKelas,
        int $nomorBaris,
        ?TahunPelajaran $tahunPelajaranAktif,
        $kelasAktif,
        array &$ringkasan,
    ): void {
        if (! $namaKelas) {
            return;
        }

        if (! $tahunPelajaranAktif) {
            $ringkasan['gagal_ditempatkan']++;
            $ringkasan['catatan'][] = 'Baris ' . $nomorBaris . ': siswa belum ditempatkan karena belum ada tahun pelajaran aktif.';

            return;
        }

        $kelas = $kelasAktif->get($this->normalisasiNamaKelas($namaKelas));

        if (! $kelas) {
            $ringkasan['gagal_ditempatkan']++;
            $ringkasan['catatan'][] = 'Baris ' . $nomorBaris . ': kelas ' . $namaKelas . ' tidak ditemukan pada tahun pelajaran aktif.';

            return;
        }

        $anggotaKelas = AnggotaKelas::where('tahun_pelajaran_id', $tahunPelajaranAktif->id)
            ->where('siswa_id', $siswa->id)
            ->first();

        if ($anggotaKelas && $anggotaKelas->kelas_id === $kelas->id) {
            return;
        }

        if ($this->kelasSudahPenuh($kelas)) {
            $ringkasan['gagal_ditempatkan']++;
            $ringkasan['catatan'][] = 'Baris ' . $nomorBaris . ': kelas ' . $kelas->nama . ' sudah penuh, siswa belum ditempatkan.';

            return;
        }

        $dataAnggotaKelas = [
            'tahun_pelajaran_id' => $tahunPelajaranAktif->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => $this->ambilNomorAbsenBerikutnya($kelas),
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => $tahunPelajaranAktif->tanggal_mulai,
            'keterangan' => 'Import Excel siswa',
        ];

        if ($anggotaKelas) {
            $anggotaKelas->update($dataAnggotaKelas);
        } else {
            AnggotaKelas::create($dataAnggotaKelas);
        }

        $ringkasan['ditempatkan_kelas']++;
    }

    private function kelasSudahPenuh(Kelas $kelas): bool
    {
        return $kelas->kapasitas
            && $kelas->anggotaKelas()->count() >= $kelas->kapasitas;
    }

    private function ambilNomorAbsenBerikutnya(Kelas $kelas): ?int
    {
        $nomorTerpakai = $kelas->anggotaKelas()
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

    private function normalisasiNamaKelas(string $namaKelas): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($namaKelas));
    }

    private function cekDuplikatDiBerkas(array $baris, array &$identitasDiBerkas): ?string
    {
        foreach (['nisn' => 'NISN', 'nis' => 'NIS', 'nik' => 'NIK'] as $field => $label) {
            $nilai = $baris['data'][$field] ?? null;

            if (! $nilai) {
                continue;
            }

            if (isset($identitasDiBerkas[$field][$nilai])) {
                return 'Baris ' . $baris['nomor_baris'] . ': ' . $label . ' ' . $nilai . ' sudah dipakai pada ' . $identitasDiBerkas[$field][$nilai] . '.';
            }

            $identitasDiBerkas[$field][$nilai] = 'baris ' . $baris['nomor_baris'] . ' (' . $baris['data']['nama_lengkap'] . ')';
        }

        return null;
    }
}
