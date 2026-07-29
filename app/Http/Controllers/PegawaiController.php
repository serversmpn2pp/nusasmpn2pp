<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Services\FotoProfilService;
use App\Support\PembacaExcelPegawai;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Throwable;

class PegawaiController extends Controller
{
    public function index(Request $request)
    {
        $kata_kunci = $request->kata_kunci;
        $status = $request->input('status', 'semua');

        if (! in_array($status, ['semua', 'aktif', 'nonaktif'], true)) {
            $status = 'semua';
        }

        $pegawai = Pegawai::query()
            ->select([
                'id',
                'nama_lengkap',
                'nip',
                'nuptk',
                'foto',
                'jenis_kelamin',
                'status_kepegawaian',
                'jenis_pegawai',
                'jabatan_utama',
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
                    $query->where('nama_lengkap', 'ilike', '%'.$kata_kunci.'%')
                        ->orWhere('nip', 'ilike', '%'.$kata_kunci.'%')
                        ->orWhere('nuptk', 'ilike', '%'.$kata_kunci.'%')
                        ->orWhere('nik', 'ilike', '%'.$kata_kunci.'%');
                });
            })
            ->orderBy('nama_lengkap')
            ->paginate(10)
            ->withQueryString();

        $jumlahPegawai = Pegawai::count();
        $jumlahAktif = Pegawai::where('aktif', true)->count();
        $jumlahNonaktif = Pegawai::where('aktif', false)->count();

        return view('pegawai.index', compact(
            'pegawai',
            'kata_kunci',
            'status',
            'jumlahPegawai',
            'jumlahAktif',
            'jumlahNonaktif',
        ));
    }

    public function create()
    {
        return view('pegawai.create');
    }

    public function store(Request $request, FotoProfilService $fotoProfilService)
    {
        $data = $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nip' => 'nullable|string|max:50|unique:pegawai,nip',
            'nuptk' => 'nullable|string|max:50|unique:pegawai,nuptk',
            'nik' => 'nullable|string|max:50|unique:pegawai,nik',
            'foto' => $fotoProfilService->aturan(),
            'jenis_kelamin' => 'nullable|in:L,P',
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'alamat' => 'nullable|string',
            'email' => 'nullable|email|max:255|unique:pegawai,email',
            'no_hp' => 'nullable|string|max:30',
            'status_kepegawaian' => 'nullable|string|max:100',
            'golongan' => 'nullable|string|max:50',
            'tanggal_mulai_kerja' => 'nullable|date',
            'tanggal_mulai_bertugas' => 'nullable|date',
            'jenis_pegawai' => 'nullable|string|max:100',
            'jabatan_utama' => 'nullable|string|max:100',
            'sumber_gaji' => 'nullable|string|max:100',
            'pendidikan_terakhir' => 'nullable|string|max:100',
            'jurusan_pendidikan' => 'nullable|string|max:150',
            'tahun_lulus' => 'nullable|integer|min:1900|max:2100',
            'keterangan' => 'nullable|string',
            'aktif' => 'nullable|boolean',
        ], $fotoProfilService->pesanValidasi());

        $data['aktif'] = $request->boolean('aktif');
        $fotoBaru = null;

        if ($request->hasFile('foto')) {
            $fotoBaru = $fotoProfilService->simpan($request->file('foto'), 'pegawai/foto');
            $data['foto'] = $fotoBaru;
        }

        try {
            Pegawai::create($data);
        } catch (Throwable $exception) {
            $fotoProfilService->hapus($fotoBaru);

            throw $exception;
        }

        return redirect()
            ->route('pegawai.index')
            ->with('berhasil', 'Data pegawai berhasil ditambahkan.');
    }

    public function show(Pegawai $pegawai)
    {
        return view('pegawai.show', compact('pegawai'));
    }

    public function edit(Pegawai $pegawai)
    {
        return view('pegawai.edit', compact('pegawai'));
    }

    public function update(Request $request, Pegawai $pegawai, FotoProfilService $fotoProfilService)
    {
        $data = $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nip' => 'nullable|string|max:50|unique:pegawai,nip,'.$pegawai->id,
            'nuptk' => 'nullable|string|max:50|unique:pegawai,nuptk,'.$pegawai->id,
            'nik' => 'nullable|string|max:50|unique:pegawai,nik,'.$pegawai->id,
            'foto' => $fotoProfilService->aturan(),
            'jenis_kelamin' => 'nullable|in:L,P',
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'alamat' => 'nullable|string',
            'email' => 'nullable|email|max:255|unique:pegawai,email,'.$pegawai->id,
            'no_hp' => 'nullable|string|max:30',
            'status_kepegawaian' => 'nullable|string|max:100',
            'golongan' => 'nullable|string|max:50',
            'tanggal_mulai_kerja' => 'nullable|date',
            'tanggal_mulai_bertugas' => 'nullable|date',
            'jenis_pegawai' => 'nullable|string|max:100',
            'jabatan_utama' => 'nullable|string|max:100',
            'sumber_gaji' => 'nullable|string|max:100',
            'pendidikan_terakhir' => 'nullable|string|max:100',
            'jurusan_pendidikan' => 'nullable|string|max:150',
            'tahun_lulus' => 'nullable|integer|min:1900|max:2100',
            'keterangan' => 'nullable|string',
            'aktif' => 'nullable|boolean',
        ], $fotoProfilService->pesanValidasi());

        $data['aktif'] = $request->boolean('aktif');
        $fotoLama = $pegawai->foto;
        $fotoBaru = null;

        if ($request->hasFile('foto')) {
            $fotoBaru = $fotoProfilService->simpan($request->file('foto'), 'pegawai/foto');
            $data['foto'] = $fotoBaru;
        } else {
            unset($data['foto']);
        }

        try {
            $pegawai->update($data);
        } catch (Throwable $exception) {
            $fotoProfilService->hapus($fotoBaru);

            throw $exception;
        }

        if ($fotoBaru) {
            $fotoProfilService->hapus($fotoLama);
        }

        return redirect()
            ->route('pegawai.index')
            ->with('berhasil', 'Data pegawai berhasil diperbarui.');
    }

    public function updateFoto(Request $request, Pegawai $pegawai, FotoProfilService $fotoProfilService)
    {
        $data = $request->validate([
            'foto' => $fotoProfilService->aturan(wajib: true),
        ], $fotoProfilService->pesanValidasi());
        $fotoLama = $pegawai->foto;
        $fotoBaru = $fotoProfilService->simpan($data['foto'], 'pegawai/foto');

        try {
            $pegawai->update(['foto' => $fotoBaru]);
        } catch (Throwable $exception) {
            $fotoProfilService->hapus($fotoBaru);

            throw $exception;
        }

        $fotoProfilService->hapus($fotoLama);

        return response()->json([
            'pesan' => 'Foto pegawai berhasil diperbarui.',
            'url' => asset('storage/'.$fotoBaru),
        ]);
    }

    public function destroy(Pegawai $pegawai)
    {
        $pegawai->update([
            'aktif' => false,
        ]);

        return redirect()
            ->route('pegawai.index')
            ->with('berhasil', 'Data pegawai berhasil dinonaktifkan.');
    }

    public function createImport()
    {
        return view('pegawai.import');
    }

    public function storeImport(Request $request, PembacaExcelPegawai $pembacaExcelPegawai)
    {
        $data = $request->validate([
            'berkas_excel' => 'required|file|mimes:xlsx|max:10240',
        ]);

        $barisPegawai = $pembacaExcelPegawai->baca($data['berkas_excel']->getRealPath());

        $ringkasan = [
            'dibaca' => count($barisPegawai),
            'ditambahkan' => 0,
            'diperbarui' => 0,
            'dilewati' => 0,
            'catatan' => [],
        ];
        $identitasDiBerkas = [
            'nip' => [],
            'nuptk' => [],
            'nik' => [],
            'email' => [],
        ];

        foreach ($barisPegawai as $baris) {
            $dataPegawai = $baris['data'];
            $duplikatBerkas = $this->cekDuplikatPegawaiDiBerkas($baris, $identitasDiBerkas);

            if ($duplikatBerkas) {
                $ringkasan['dilewati']++;
                $ringkasan['catatan'][] = $duplikatBerkas;

                continue;
            }

            try {
                $pegawai = $this->cariPegawaiUntukImport($dataPegawai);

                if ($pegawai) {
                    $pegawai->update($dataPegawai);
                    $ringkasan['diperbarui']++;
                } else {
                    Pegawai::create($dataPegawai);
                    $ringkasan['ditambahkan']++;
                }
            } catch (QueryException $exception) {
                $ringkasan['dilewati']++;
                $ringkasan['catatan'][] = 'Baris '.$baris['nomor_baris'].': data tidak disimpan karena bentrok dengan data unik yang sudah ada.';
            }
        }

        return redirect()
            ->route('pegawai.index')
            ->with('berhasil', 'Import Excel pegawai selesai.')
            ->with('ringkasan_import', $ringkasan);
    }

    private function cariPegawaiUntukImport(array $dataPegawai): ?Pegawai
    {
        if (
            empty($dataPegawai['nip'])
            && empty($dataPegawai['nuptk'])
            && empty($dataPegawai['nik'])
            && empty($dataPegawai['email'])
        ) {
            return null;
        }

        return Pegawai::query()
            ->when($dataPegawai['nip'] ?? null, function ($query, $nip) {
                $query->orWhere('nip', $nip);
            })
            ->when($dataPegawai['nuptk'] ?? null, function ($query, $nuptk) {
                $query->orWhere('nuptk', $nuptk);
            })
            ->when($dataPegawai['nik'] ?? null, function ($query, $nik) {
                $query->orWhere('nik', $nik);
            })
            ->when($dataPegawai['email'] ?? null, function ($query, $email) {
                $query->orWhere('email', $email);
            })
            ->first();
    }

    private function cekDuplikatPegawaiDiBerkas(array $baris, array &$identitasDiBerkas): ?string
    {
        foreach (['nip' => 'NIP', 'nuptk' => 'NUPTK', 'nik' => 'NIK', 'email' => 'Email'] as $field => $label) {
            $nilai = $baris['data'][$field] ?? null;

            if (! $nilai) {
                continue;
            }

            if (isset($identitasDiBerkas[$field][$nilai])) {
                return 'Baris '.$baris['nomor_baris'].': '.$label.' '.$nilai.' sudah dipakai pada '.$identitasDiBerkas[$field][$nilai].'.';
            }

            $identitasDiBerkas[$field][$nilai] = 'baris '.$baris['nomor_baris'].' ('.$baris['data']['nama_lengkap'].')';
        }

        return null;
    }
}
