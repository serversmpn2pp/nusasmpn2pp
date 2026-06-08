<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\PesertaUjianCbt;
use App\Models\RuangUjianCbt;
use App\Models\SesiUjianCbt;
use App\Models\UjianCbt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RuangUjianCbtController extends Controller
{
    public function index(Request $request, UjianCbt $ujianCbt)
    {
        $data = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'sesi_ujian_cbt_id' => ['nullable', 'integer', 'exists:sesi_ujian_cbt,id'],
            'jadwal_ujian_cbt_id' => ['nullable', 'integer', 'exists:jadwal_ujian_cbt,id'],
            'ruang_ujian_cbt_id' => ['nullable', 'integer', 'exists:ruang_ujian_cbt,id'],
        ]);

        $kelasId = $data['kelas_id'] ?? null;
        $sesiUjianCbtId = $data['sesi_ujian_cbt_id'] ?? null;
        $jadwalUjianCbtId = $data['jadwal_ujian_cbt_id'] ?? null;
        $ruangUjianCbtId = $data['ruang_ujian_cbt_id'] ?? null;
        $this->pastikanJadwalMilikUjian($ujianCbt, $jadwalUjianCbtId);

        $ujianCbt->load([
            'jenisUjianCbt',
            'tahunPelajaran',
            'mataPelajaran',
            'kelasUjianCbt.kelas',
            'sesiUjianCbt',
        ]);

        $kelasPeserta = $ujianCbt->kelasUjianCbt
            ->sortBy(fn ($item) => $item->kelas?->nama)
            ->values();
        $sesiUjianCbt = $ujianCbt->sesiUjianCbt
            ->sortBy(fn (SesiUjianCbt $sesi) => sprintf(
                '%s|%s',
                $sesi->waktu_mulai?->format('YmdHis') ?? '99999999999999',
                $sesi->kode,
            ))
            ->values();
        $jadwalUjianCbt = $ujianCbt->jadwalUjianCbt()
            ->with(['kegiatanUjianCbt', 'mataPelajaran', 'kelas'])
            ->orderBy('tanggal')
            ->orderBy('waktu_mulai')
            ->orderBy('urutan')
            ->get();

        $ruangUjianCbt = $ujianCbt->ruangUjianCbt()
            ->with([
                'sesiUjianCbt',
                'jadwalUjianCbt.kegiatanUjianCbt',
                'jadwalUjianCbt.mataPelajaran',
                'pengawasUtama',
                'pengawasPendamping',
                'dikunciOleh',
                'buktiDaftarHadirDiunggahOleh',
                'buktiBeritaAcaraDiunggahOleh',
                'pesertaUjianCbt',
            ])
            ->when($sesiUjianCbtId, fn ($query) => $query->where('sesi_ujian_cbt_id', $sesiUjianCbtId))
            ->when($jadwalUjianCbtId, fn ($query) => $query->where('jadwal_ujian_cbt_id', $jadwalUjianCbtId))
            ->orderBy('kode')
            ->get();

        if ($ruangUjianCbtId && ! $ruangUjianCbt->pluck('id')->contains((int) $ruangUjianCbtId)) {
            $ruangUjianCbtId = null;
        }

        $pesertaUjianCbt = $ujianCbt->pesertaUjianCbt()
            ->with([
                'sesiUjianCbt',
                'ruangUjianCbt',
                'kelasUjianCbt.kelas',
                'anggotaKelas.siswa',
                'akunPesertaCbt',
            ])
            ->when($kelasId, fn ($query) => $query->whereHas(
                'kelasUjianCbt',
                fn ($query) => $query->where('kelas_id', $kelasId),
            ))
            ->when($sesiUjianCbtId, fn ($query) => $query->where('sesi_ujian_cbt_id', $sesiUjianCbtId))
            ->when($jadwalUjianCbtId, fn ($query) => $query->where(function ($query) use ($jadwalUjianCbtId) {
                $query->whereHas(
                    'ruangUjianCbt',
                    fn ($query) => $query->where('jadwal_ujian_cbt_id', $jadwalUjianCbtId),
                )->orWhereNull('ruang_ujian_cbt_id');
            }))
            ->when($ruangUjianCbtId, fn ($query) => $query->where('ruang_ujian_cbt_id', $ruangUjianCbtId))
            ->get()
            ->sortBy(fn (PesertaUjianCbt $item) => sprintf(
                '%s|%s|%05d|%05d|%s',
                $item->ruangUjianCbt?->kode ?? 'ZZZ',
                $item->kelasUjianCbt?->kelas?->nama ?? '',
                $item->nomor_meja ?? 999,
                $item->anggotaKelas?->nomor_absen ?? 999,
                $item->anggotaKelas?->siswa?->nama_lengkap ?? '',
            ))
            ->values();

        $daftarPegawai = Pegawai::query()
            ->where('aktif', true)
            ->orderBy('nama_lengkap')
            ->get();

        return view('ujian-cbt.ruang.index', [
            'ujianCbt' => $ujianCbt,
            'kelasPeserta' => $kelasPeserta,
            'sesiUjianCbt' => $sesiUjianCbt,
            'jadwalUjianCbt' => $jadwalUjianCbt,
            'ruangUjianCbt' => $ruangUjianCbt,
            'pesertaUjianCbt' => $pesertaUjianCbt,
            'daftarPegawai' => $daftarPegawai,
            'kelasId' => $kelasId,
            'sesiUjianCbtId' => $sesiUjianCbtId,
            'jadwalUjianCbtId' => $jadwalUjianCbtId,
            'ruangUjianCbtId' => $ruangUjianCbtId,
            'daftarStatusRuang' => RuangUjianCbt::DAFTAR_STATUS,
            'daftarStatusKehadiran' => PesertaUjianCbt::DAFTAR_STATUS_KEHADIRAN,
            'ringkasan' => $this->ringkasan($ujianCbt),
        ]);
    }

    public function cetak(Request $request, UjianCbt $ujianCbt)
    {
        $data = $request->validate([
            'sesi_ujian_cbt_id' => ['nullable', 'integer', 'exists:sesi_ujian_cbt,id'],
            'jadwal_ujian_cbt_id' => ['nullable', 'integer', 'exists:jadwal_ujian_cbt,id'],
            'ruang_ujian_cbt_id' => ['nullable', 'integer', 'exists:ruang_ujian_cbt,id'],
        ]);

        $sesiUjianCbtId = $data['sesi_ujian_cbt_id'] ?? null;
        $jadwalUjianCbtId = $data['jadwal_ujian_cbt_id'] ?? null;
        $ruangUjianCbtId = $data['ruang_ujian_cbt_id'] ?? null;
        $this->pastikanSesiMilikUjian($ujianCbt, $sesiUjianCbtId);
        $this->pastikanJadwalMilikUjian($ujianCbt, $jadwalUjianCbtId);

        if ($ruangUjianCbtId) {
            $ruangDipilih = RuangUjianCbt::findOrFail($ruangUjianCbtId);
            $this->pastikanRuangMilikUjian($ujianCbt, $ruangDipilih);
        }

        $ujianCbt->load([
            'jenisUjianCbt',
            'tahunPelajaran',
            'mataPelajaran',
        ]);

        $ruangUjianCbt = $ujianCbt->ruangUjianCbt()
            ->with([
                'sesiUjianCbt',
                'jadwalUjianCbt.kegiatanUjianCbt',
                'jadwalUjianCbt.mataPelajaran',
                'pengawasUtama',
                'pengawasPendamping',
                'pesertaUjianCbt.sesiUjianCbt',
                'pesertaUjianCbt.kelasUjianCbt.kelas',
                'pesertaUjianCbt.anggotaKelas.siswa',
                'pesertaUjianCbt.akunPesertaCbt',
            ])
            ->when($sesiUjianCbtId, fn ($query) => $query->where('sesi_ujian_cbt_id', $sesiUjianCbtId))
            ->when($jadwalUjianCbtId, fn ($query) => $query->where('jadwal_ujian_cbt_id', $jadwalUjianCbtId))
            ->when($ruangUjianCbtId, fn ($query) => $query->whereKey($ruangUjianCbtId))
            ->orderBy('kode')
            ->get()
            ->map(function (RuangUjianCbt $ruang) {
                $peserta = $ruang->pesertaUjianCbt
                    ->sortBy(fn (PesertaUjianCbt $item) => sprintf(
                        '%05d|%s|%05d|%s',
                        $item->nomor_meja ?? 999,
                        $item->kelasUjianCbt?->kelas?->nama ?? '',
                        $item->anggotaKelas?->nomor_absen ?? 999,
                        $item->anggotaKelas?->siswa?->nama_lengkap ?? '',
                    ))
                    ->values();

                $ruang->setRelation('pesertaUjianCbt', $peserta);

                return $ruang;
            });

        return view('ujian-cbt.ruang.cetak', [
            'ujianCbt' => $ujianCbt,
            'ruangUjianCbt' => $ruangUjianCbt,
            'sesiUjianCbtId' => $sesiUjianCbtId,
            'jadwalUjianCbtId' => $jadwalUjianCbtId,
            'ruangUjianCbtId' => $ruangUjianCbtId,
            'daftarStatusKehadiran' => PesertaUjianCbt::DAFTAR_STATUS_KEHADIRAN,
        ]);
    }

    public function store(Request $request, UjianCbt $ujianCbt)
    {
        $data = $this->validasiRuang($request, $ujianCbt);
        $data = $this->rapikanRuang($data);
        $this->pastikanKodeRuangUnik($ujianCbt, $data);

        $ujianCbt->ruangUjianCbt()->create($data);

        return redirect()
            ->route('ujian-cbt.ruang.index', $this->queryRuang(
                $ujianCbt,
                $data['sesi_ujian_cbt_id'] ?? null,
                null,
                $data['jadwal_ujian_cbt_id'] ?? null,
            ))
            ->with('berhasil', 'Ruang CBT berhasil ditambahkan.');
    }

    public function storeMassal(Request $request, UjianCbt $ujianCbt)
    {
        $data = $request->validate([
            'sesi_ujian_cbt_id' => ['nullable', 'integer', 'exists:sesi_ujian_cbt,id'],
            'jadwal_ujian_cbt_id' => ['nullable', 'integer', 'exists:jadwal_ujian_cbt,id'],
            'prefix' => ['required', 'string', 'max:24'],
            'jumlah_ruang' => ['required', 'integer', 'min:1', 'max:30'],
            'kapasitas' => ['required', 'integer', 'min:1', 'max:200'],
            'lokasi' => ['nullable', 'string', 'max:255'],
        ]);
        $this->pastikanSesiMilikUjian($ujianCbt, $data['sesi_ujian_cbt_id'] ?? null);
        $this->pastikanJadwalMilikUjian($ujianCbt, $data['jadwal_ujian_cbt_id'] ?? null);

        $prefix = mb_strtoupper(trim($data['prefix']));
        $jumlah = 0;

        DB::transaction(function () use ($ujianCbt, $data, $prefix, &$jumlah) {
            for ($i = 1; $i <= (int) $data['jumlah_ruang']; $i++) {
                $kode = $this->buatKodeRuangBerikutnya(
                    $ujianCbt,
                    $data['sesi_ujian_cbt_id'] ?? null,
                    $data['jadwal_ujian_cbt_id'] ?? null,
                    $prefix,
                    $i,
                );

                $ujianCbt->ruangUjianCbt()->create([
                    'sesi_ujian_cbt_id' => $data['sesi_ujian_cbt_id'] ?? null,
                    'jadwal_ujian_cbt_id' => $data['jadwal_ujian_cbt_id'] ?? null,
                    'kode' => $kode,
                    'nama' => 'Ruang ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'lokasi' => filled($data['lokasi'] ?? null) ? trim($data['lokasi']) : null,
                    'kapasitas' => (int) $data['kapasitas'],
                    'status' => 'draft',
                ]);
                $jumlah++;
            }
        });

        return redirect()
            ->route('ujian-cbt.ruang.index', $this->queryRuang(
                $ujianCbt,
                $data['sesi_ujian_cbt_id'] ?? null,
                null,
                $data['jadwal_ujian_cbt_id'] ?? null,
            ))
            ->with('berhasil', "{$jumlah} ruang CBT berhasil dibuat.");
    }

    public function bagiOtomatis(Request $request, UjianCbt $ujianCbt)
    {
        $data = $request->validate([
            'sesi_ujian_cbt_id' => ['required', 'integer', 'exists:sesi_ujian_cbt,id'],
            'jadwal_ujian_cbt_id' => ['nullable', 'integer', 'exists:jadwal_ujian_cbt,id'],
        ]);
        $this->pastikanSesiMilikUjian($ujianCbt, $data['sesi_ujian_cbt_id']);
        $this->pastikanJadwalMilikUjian($ujianCbt, $data['jadwal_ujian_cbt_id'] ?? null);

        $ruang = $ujianCbt->ruangUjianCbt()
            ->where('sesi_ujian_cbt_id', $data['sesi_ujian_cbt_id'])
            ->when($data['jadwal_ujian_cbt_id'] ?? null, fn ($query, $jadwalId) => $query->where('jadwal_ujian_cbt_id', $jadwalId))
            ->where('status', '!=', 'nonaktif')
            ->orderBy('kode')
            ->get();

        if ($ruang->isEmpty()) {
            throw ValidationException::withMessages([
                'ruang' => 'Buat minimal satu ruang untuk sesi ini sebelum membagi peserta.',
            ]);
        }

        if ($ruang->contains(fn (RuangUjianCbt $item) => $item->terkunci())) {
            throw ValidationException::withMessages([
                'ruang' => 'Ada ruang yang sudah dikunci. Buka kunci ruang terlebih dahulu sebelum pembagian otomatis.',
            ]);
        }

        $peserta = $ujianCbt->pesertaUjianCbt()
            ->with(['kelasUjianCbt.kelas', 'anggotaKelas.siswa'])
            ->where('sesi_ujian_cbt_id', $data['sesi_ujian_cbt_id'])
            ->get()
            ->sortBy(fn (PesertaUjianCbt $item) => sprintf(
                '%s|%05d|%s',
                $item->kelasUjianCbt?->kelas?->nama ?? '',
                $item->anggotaKelas?->nomor_absen ?? 999,
                $item->anggotaKelas?->siswa?->nama_lengkap ?? '',
            ))
            ->values();

        $jumlahDibagi = 0;
        $jumlahTidakTertampung = 0;

        DB::transaction(function () use ($peserta, $ruang, &$jumlahDibagi, &$jumlahTidakTertampung) {
            $ruangIndex = 0;
            $nomorMeja = 1;

            foreach ($peserta as $item) {
                while ($ruangIndex < $ruang->count()) {
                    $kapasitas = $ruang[$ruangIndex]->kapasitas ?: PHP_INT_MAX;

                    if ($nomorMeja <= $kapasitas) {
                        break;
                    }

                    $ruangIndex++;
                    $nomorMeja = 1;
                }

                if (! $ruang->has($ruangIndex)) {
                    $item->update([
                        'ruang_ujian_cbt_id' => null,
                        'nomor_meja' => null,
                    ]);
                    $jumlahTidakTertampung++;

                    continue;
                }

                $item->update([
                    'ruang_ujian_cbt_id' => $ruang[$ruangIndex]->id,
                    'nomor_meja' => $nomorMeja,
                ]);
                $jumlahDibagi++;
                $nomorMeja++;
            }
        });

        $pesan = "{$jumlahDibagi} peserta berhasil dibagi ke ruang dan nomor meja.";

        if ($jumlahTidakTertampung) {
            $pesan .= " {$jumlahTidakTertampung} peserta belum tertampung karena kapasitas ruang kurang.";
        }

        return redirect()
            ->route('ujian-cbt.ruang.index', $this->queryRuang(
                $ujianCbt,
                $data['sesi_ujian_cbt_id'],
                null,
                $data['jadwal_ujian_cbt_id'] ?? null,
            ))
            ->with('berhasil', $pesan);
    }

    public function updatePeserta(Request $request, UjianCbt $ujianCbt)
    {
        $data = $request->validate([
            'filter_sesi_ujian_cbt_id' => ['nullable', 'integer', 'exists:sesi_ujian_cbt,id'],
            'filter_jadwal_ujian_cbt_id' => ['nullable', 'integer', 'exists:jadwal_ujian_cbt,id'],
            'filter_ruang_ujian_cbt_id' => ['nullable', 'integer', 'exists:ruang_ujian_cbt,id'],
            'peserta' => ['nullable', 'array'],
            'peserta.*.ruang_ujian_cbt_id' => ['nullable', 'integer', 'exists:ruang_ujian_cbt,id'],
            'peserta.*.nomor_meja' => ['nullable', 'integer', 'min:1', 'max:999'],
            'peserta.*.status_kehadiran_ujian' => ['required', Rule::in(array_keys(PesertaUjianCbt::DAFTAR_STATUS_KEHADIRAN))],
            'peserta.*.catatan_kehadiran_ujian' => ['nullable', 'string', 'max:1000'],
        ]);
        $filterSesiId = $data['filter_sesi_ujian_cbt_id'] ?? null;
        $filterJadwalId = $data['filter_jadwal_ujian_cbt_id'] ?? null;
        $filterRuangId = filled($data['filter_ruang_ujian_cbt_id'] ?? null) ? (int) $data['filter_ruang_ujian_cbt_id'] : null;
        $this->pastikanSesiMilikUjian($ujianCbt, $filterSesiId);
        $this->pastikanJadwalMilikUjian($ujianCbt, $filterJadwalId);

        if ($filterRuangId) {
            $this->pastikanRuangMilikUjian($ujianCbt, RuangUjianCbt::findOrFail($filterRuangId));
        }

        $baris = collect($data['peserta'] ?? []);

        if ($baris->isEmpty()) {
            return redirect()
                ->route('ujian-cbt.ruang.index', $this->queryRuang($ujianCbt, $filterSesiId, $filterRuangId, $filterJadwalId))
                ->with('berhasil', 'Belum ada peserta yang perlu diperbarui.');
        }

        $peserta = $ujianCbt->pesertaUjianCbt()
            ->whereIn('id', $baris->keys())
            ->get()
            ->keyBy('id');
        $ruang = $ujianCbt->ruangUjianCbt()
            ->whereIn('id', $baris->pluck('ruang_ujian_cbt_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        if ($peserta->count() !== $baris->count()) {
            throw ValidationException::withMessages([
                'peserta' => 'Ada peserta yang tidak termasuk dalam paket CBT ini.',
            ]);
        }

        $this->pastikanRuangDanNomorMejaValid($baris, $peserta, $ruang);
        $this->pastikanPerubahanPesertaTidakMenggangguRuangTerkunci($baris, $peserta, $ruang);

        DB::transaction(function () use ($request, $baris, $peserta) {
            foreach ($baris as $pesertaId => $item) {
                $pesertaUjian = $peserta[$pesertaId];
                $statusKehadiran = $item['status_kehadiran_ujian'];
                $dataUpdate = [
                    'ruang_ujian_cbt_id' => filled($item['ruang_ujian_cbt_id'] ?? null) ? (int) $item['ruang_ujian_cbt_id'] : null,
                    'nomor_meja' => filled($item['nomor_meja'] ?? null) ? (int) $item['nomor_meja'] : null,
                    'status_kehadiran_ujian' => $statusKehadiran,
                    'catatan_kehadiran_ujian' => filled($item['catatan_kehadiran_ujian'] ?? null) ? trim($item['catatan_kehadiran_ujian']) : null,
                ];

                if ($statusKehadiran === 'belum_absen') {
                    $dataUpdate['absen_ujian_pada'] = null;
                    $dataUpdate['absen_ujian_oleh_pengguna_id'] = null;
                } elseif (
                    $pesertaUjian->status_kehadiran_ujian !== $statusKehadiran
                    || ! $pesertaUjian->absen_ujian_pada
                ) {
                    $dataUpdate['absen_ujian_pada'] = now();
                    $dataUpdate['absen_ujian_oleh_pengguna_id'] = $request->user()?->id;
                }

                $pesertaUjian->update($dataUpdate);
            }
        });

        return redirect()
            ->route('ujian-cbt.ruang.index', $this->queryRuang($ujianCbt, $filterSesiId, $filterRuangId, $filterJadwalId))
            ->with('berhasil', 'Ruang, nomor meja, dan absensi peserta CBT berhasil disimpan.');
    }

    public function update(Request $request, UjianCbt $ujianCbt, RuangUjianCbt $ruangUjianCbt)
    {
        $this->pastikanRuangMilikUjian($ujianCbt, $ruangUjianCbt);
        $this->pastikanRuangBelumTerkunci($ruangUjianCbt);
        $data = $this->validasiRuang($request, $ujianCbt, $ruangUjianCbt);
        $data = $this->rapikanRuang($data);
        $this->pastikanKodeRuangUnik($ujianCbt, $data, $ruangUjianCbt);

        $ruangUjianCbt->update($data);

        return redirect()
            ->route('ujian-cbt.ruang.index', $this->queryRuang(
                $ujianCbt,
                $data['sesi_ujian_cbt_id'] ?? null,
                $ruangUjianCbt->id,
                $data['jadwal_ujian_cbt_id'] ?? null,
            ))
            ->with('berhasil', 'Ruang dan berita acara CBT berhasil disimpan.');
    }

    public function updateBukti(Request $request, UjianCbt $ujianCbt, RuangUjianCbt $ruangUjianCbt)
    {
        $this->pastikanRuangMilikUjian($ujianCbt, $ruangUjianCbt);
        $data = $request->validate([
            'bukti_daftar_hadir' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'bukti_berita_acara' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        $daftarFile = collect([
            'daftar_hadir' => $request->file('bukti_daftar_hadir'),
            'berita_acara' => $request->file('bukti_berita_acara'),
        ])->filter();

        if ($daftarFile->isEmpty()) {
            throw ValidationException::withMessages([
                'bukti' => 'Unggah minimal satu bukti daftar hadir atau berita acara.',
            ]);
        }

        $fileBaru = [];
        $fileLama = [];

        try {
            foreach ($daftarFile as $jenis => $file) {
                $lokasiFile = $file->store("cbt/{$ujianCbt->id}/ruang/{$ruangUjianCbt->id}/bukti", 'local');
                $fileBaru[$jenis] = [
                    'file' => $file,
                    'lokasi_file' => $lokasiFile,
                ];
                $prefix = $this->prefixBukti($jenis);
                $fileLama[] = $ruangUjianCbt->getAttribute("{$prefix}_lokasi_file");
            }

            DB::transaction(function () use ($request, $ruangUjianCbt, $fileBaru) {
                $dataUpdate = [];
                $waktuUnggah = now();

                foreach ($fileBaru as $jenis => $item) {
                    $dataUpdate = array_merge(
                        $dataUpdate,
                        $this->dataBuktiFile($jenis, $item['file'], $item['lokasi_file'], $request->user()?->id, $waktuUnggah),
                    );
                }

                $ruangUjianCbt->update($dataUpdate);
            });
        } catch (\Throwable $exception) {
            foreach ($fileBaru as $item) {
                Storage::disk('local')->delete($item['lokasi_file']);
            }

            throw $exception;
        }

        foreach (array_filter($fileLama) as $lokasiFileLama) {
            Storage::disk('local')->delete($lokasiFileLama);
        }

        return redirect()
            ->route('ujian-cbt.ruang.index', $this->queryRuang(
                $ujianCbt,
                $ruangUjianCbt->sesi_ujian_cbt_id,
                $ruangUjianCbt->id,
                $ruangUjianCbt->jadwal_ujian_cbt_id,
            ))
            ->with('berhasil', 'Bukti daftar hadir/berita acara berhasil diunggah.');
    }

    public function downloadBukti(UjianCbt $ujianCbt, RuangUjianCbt $ruangUjianCbt, string $jenis)
    {
        $this->pastikanRuangMilikUjian($ujianCbt, $ruangUjianCbt);
        $prefix = $this->prefixBukti($jenis);
        $lokasiFile = $ruangUjianCbt->getAttribute("{$prefix}_lokasi_file");
        $namaFile = $ruangUjianCbt->getAttribute("{$prefix}_nama_file_asli") ?: basename((string) $lokasiFile);

        abort_unless($lokasiFile && Storage::disk('local')->exists($lokasiFile), 404);

        return Storage::disk('local')->download($lokasiFile, $namaFile);
    }

    public function destroyBukti(UjianCbt $ujianCbt, RuangUjianCbt $ruangUjianCbt, string $jenis)
    {
        $this->pastikanRuangMilikUjian($ujianCbt, $ruangUjianCbt);
        $prefix = $this->prefixBukti($jenis);
        $lokasiFile = $ruangUjianCbt->getAttribute("{$prefix}_lokasi_file");

        $ruangUjianCbt->update($this->dataBuktiKosong($prefix));

        if ($lokasiFile) {
            Storage::disk('local')->delete($lokasiFile);
        }

        return redirect()
            ->route('ujian-cbt.ruang.index', $this->queryRuang(
                $ujianCbt,
                $ruangUjianCbt->sesi_ujian_cbt_id,
                $ruangUjianCbt->id,
                $ruangUjianCbt->jadwal_ujian_cbt_id,
            ))
            ->with('berhasil', 'Bukti berhasil dihapus.');
    }

    public function destroy(UjianCbt $ujianCbt, RuangUjianCbt $ruangUjianCbt)
    {
        $this->pastikanRuangMilikUjian($ujianCbt, $ruangUjianCbt);
        $this->pastikanRuangBelumTerkunci($ruangUjianCbt);

        $sesiId = $ruangUjianCbt->sesi_ujian_cbt_id;
        $jadwalId = $ruangUjianCbt->jadwal_ujian_cbt_id;
        $kodeRuang = $ruangUjianCbt->kode;
        $jumlahPeserta = $ruangUjianCbt->pesertaUjianCbt()->count();

        DB::transaction(function () use ($ruangUjianCbt) {
            $ruangUjianCbt->pesertaUjianCbt()->update([
                'ruang_ujian_cbt_id' => null,
                'nomor_meja' => null,
            ]);

            $ruangUjianCbt->delete();
        });

        $pesan = "Ruang {$kodeRuang} berhasil dihapus.";

        if ($jumlahPeserta) {
            $pesan .= " {$jumlahPeserta} peserta dikembalikan menjadi belum ditempatkan.";
        }

        return redirect()
            ->route('ujian-cbt.ruang.index', $this->queryRuang($ujianCbt, $sesiId, null, $jadwalId))
            ->with('berhasil', $pesan);
    }

    public function kunci(Request $request, UjianCbt $ujianCbt, RuangUjianCbt $ruangUjianCbt)
    {
        $this->pastikanRuangMilikUjian($ujianCbt, $ruangUjianCbt);

        if (! $ruangUjianCbt->terkunci()) {
            $ruangUjianCbt->update([
                'dikunci_pada' => now(),
                'dikunci_oleh_pengguna_id' => $request->user()?->id,
                'status' => $ruangUjianCbt->status === 'draft' ? 'siap' : $ruangUjianCbt->status,
            ]);
        }

        return redirect()
            ->route('ujian-cbt.ruang.index', $this->queryRuang(
                $ujianCbt,
                $ruangUjianCbt->sesi_ujian_cbt_id,
                $ruangUjianCbt->id,
                $ruangUjianCbt->jadwal_ujian_cbt_id,
            ))
            ->with('berhasil', "Ruang {$ruangUjianCbt->kode} berhasil dikunci.");
    }

    public function bukaKunci(UjianCbt $ujianCbt, RuangUjianCbt $ruangUjianCbt)
    {
        $this->pastikanRuangMilikUjian($ujianCbt, $ruangUjianCbt);

        $ruangUjianCbt->update([
            'dikunci_pada' => null,
            'dikunci_oleh_pengguna_id' => null,
        ]);

        return redirect()
            ->route('ujian-cbt.ruang.index', $this->queryRuang(
                $ujianCbt,
                $ruangUjianCbt->sesi_ujian_cbt_id,
                $ruangUjianCbt->id,
                $ruangUjianCbt->jadwal_ujian_cbt_id,
            ))
            ->with('berhasil', "Kunci ruang {$ruangUjianCbt->kode} berhasil dibuka.");
    }

    private function prefixBukti(string $jenis): string
    {
        return match ($jenis) {
            'daftar-hadir', 'daftar_hadir' => 'bukti_daftar_hadir',
            'berita-acara', 'berita_acara' => 'bukti_berita_acara',
            default => abort(404),
        };
    }

    private function dataBuktiFile(string $jenis, $file, string $lokasiFile, ?int $penggunaId, $waktuUnggah): array
    {
        $prefix = $this->prefixBukti($jenis);

        return [
            "{$prefix}_lokasi_file" => $lokasiFile,
            "{$prefix}_nama_file_asli" => $file->getClientOriginalName(),
            "{$prefix}_tipe_file" => $file->getMimeType(),
            "{$prefix}_ukuran_file" => $file->getSize(),
            "{$prefix}_diunggah_pada" => $waktuUnggah,
            "{$prefix}_diunggah_oleh_pengguna_id" => $penggunaId,
        ];
    }

    private function dataBuktiKosong(string $prefix): array
    {
        return [
            "{$prefix}_lokasi_file" => null,
            "{$prefix}_nama_file_asli" => null,
            "{$prefix}_tipe_file" => null,
            "{$prefix}_ukuran_file" => null,
            "{$prefix}_diunggah_pada" => null,
            "{$prefix}_diunggah_oleh_pengguna_id" => null,
        ];
    }

    private function validasiRuang(Request $request, UjianCbt $ujianCbt, ?RuangUjianCbt $ruangUjianCbt = null): array
    {
        $data = $request->validate([
            'sesi_ujian_cbt_id' => ['nullable', 'integer', 'exists:sesi_ujian_cbt,id'],
            'jadwal_ujian_cbt_id' => ['nullable', 'integer', 'exists:jadwal_ujian_cbt,id'],
            'kode' => ['required', 'string', 'max:40'],
            'nama' => ['required', 'string', 'max:120'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'kapasitas' => ['nullable', 'integer', 'min:1', 'max:200'],
            'pengawas_utama_pegawai_id' => ['nullable', 'integer', 'exists:pegawai,id'],
            'pengawas_pendamping_pegawai_id' => ['nullable', 'integer', 'exists:pegawai,id'],
            'waktu_mulai_aktual' => ['nullable', 'date'],
            'waktu_selesai_aktual' => ['nullable', 'date', 'after_or_equal:waktu_mulai_aktual'],
            'berita_acara' => ['nullable', 'string'],
            'hambatan' => ['nullable', 'string'],
            'tindak_lanjut' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'status' => ['required', Rule::in(array_keys(RuangUjianCbt::DAFTAR_STATUS))],
        ]);
        $this->pastikanSesiMilikUjian($ujianCbt, $data['sesi_ujian_cbt_id'] ?? null);
        $this->pastikanJadwalMilikUjian($ujianCbt, $data['jadwal_ujian_cbt_id'] ?? null);

        return $data;
    }

    private function rapikanRuang(array $data): array
    {
        return [
            'sesi_ujian_cbt_id' => filled($data['sesi_ujian_cbt_id'] ?? null) ? (int) $data['sesi_ujian_cbt_id'] : null,
            'jadwal_ujian_cbt_id' => filled($data['jadwal_ujian_cbt_id'] ?? null) ? (int) $data['jadwal_ujian_cbt_id'] : null,
            'kode' => mb_strtoupper(trim($data['kode'])),
            'nama' => trim($data['nama']),
            'lokasi' => filled($data['lokasi'] ?? null) ? trim($data['lokasi']) : null,
            'kapasitas' => filled($data['kapasitas'] ?? null) ? (int) $data['kapasitas'] : null,
            'pengawas_utama_pegawai_id' => filled($data['pengawas_utama_pegawai_id'] ?? null) ? (int) $data['pengawas_utama_pegawai_id'] : null,
            'pengawas_pendamping_pegawai_id' => filled($data['pengawas_pendamping_pegawai_id'] ?? null) ? (int) $data['pengawas_pendamping_pegawai_id'] : null,
            'waktu_mulai_aktual' => $data['waktu_mulai_aktual'] ?? null,
            'waktu_selesai_aktual' => $data['waktu_selesai_aktual'] ?? null,
            'berita_acara' => filled($data['berita_acara'] ?? null) ? trim($data['berita_acara']) : null,
            'hambatan' => filled($data['hambatan'] ?? null) ? trim($data['hambatan']) : null,
            'tindak_lanjut' => filled($data['tindak_lanjut'] ?? null) ? trim($data['tindak_lanjut']) : null,
            'catatan' => filled($data['catatan'] ?? null) ? trim($data['catatan']) : null,
            'status' => $data['status'],
        ];
    }

    private function pastikanSesiMilikUjian(UjianCbt $ujianCbt, int|string|null $sesiUjianCbtId): void
    {
        if (! $sesiUjianCbtId) {
            return;
        }

        $milikUjian = $ujianCbt->sesiUjianCbt()
            ->whereKey($sesiUjianCbtId)
            ->exists();

        if (! $milikUjian) {
            throw ValidationException::withMessages([
                'sesi_ujian_cbt_id' => 'Sesi tidak termasuk dalam paket CBT ini.',
            ]);
        }
    }

    private function pastikanJadwalMilikUjian(UjianCbt $ujianCbt, int|string|null $jadwalUjianCbtId): void
    {
        if (! $jadwalUjianCbtId) {
            return;
        }

        $milikUjian = $ujianCbt->jadwalUjianCbt()
            ->whereKey($jadwalUjianCbtId)
            ->exists();

        if (! $milikUjian) {
            throw ValidationException::withMessages([
                'jadwal_ujian_cbt_id' => 'Jadwal tidak termasuk dalam paket CBT ini.',
            ]);
        }
    }

    private function pastikanRuangMilikUjian(UjianCbt $ujianCbt, RuangUjianCbt $ruangUjianCbt): void
    {
        abort_unless((int) $ruangUjianCbt->ujian_cbt_id === (int) $ujianCbt->id, 404);
    }

    private function pastikanRuangBelumTerkunci(RuangUjianCbt $ruangUjianCbt): void
    {
        if (! $ruangUjianCbt->terkunci()) {
            return;
        }

        throw ValidationException::withMessages([
            'ruang' => 'Ruang ujian sudah dikunci. Buka kunci ruang terlebih dahulu jika perlu revisi.',
        ]);
    }

    private function pastikanKodeRuangUnik(UjianCbt $ujianCbt, array $data, ?RuangUjianCbt $abaikan = null): void
    {
        $sudahAda = $ujianCbt->ruangUjianCbt()
            ->where('kode', $data['kode'])
            ->when(
                $data['sesi_ujian_cbt_id'] ?? null,
                fn ($query, $sesiId) => $query->where('sesi_ujian_cbt_id', $sesiId),
                fn ($query) => $query->whereNull('sesi_ujian_cbt_id'),
            )
            ->when(
                $data['jadwal_ujian_cbt_id'] ?? null,
                fn ($query, $jadwalId) => $query->where('jadwal_ujian_cbt_id', $jadwalId),
                fn ($query) => $query->whereNull('jadwal_ujian_cbt_id'),
            )
            ->when($abaikan, fn ($query) => $query->whereKeyNot($abaikan->id))
            ->exists();

        if ($sudahAda) {
            throw ValidationException::withMessages([
                'kode' => 'Kode ruang sudah digunakan pada sesi/jadwal ini.',
            ]);
        }
    }

    private function buatKodeRuangBerikutnya(
        UjianCbt $ujianCbt,
        int|string|null $sesiUjianCbtId,
        int|string|null $jadwalUjianCbtId,
        string $prefix,
        int $urutan,
    ): string {
        $basis = mb_strtoupper($prefix) . '-' . str_pad((string) $urutan, 2, '0', STR_PAD_LEFT);
        $kode = $basis;
        $suffix = 2;

        while ($ujianCbt->ruangUjianCbt()
            ->where('kode', $kode)
            ->when(
                $sesiUjianCbtId,
                fn ($query, $sesiId) => $query->where('sesi_ujian_cbt_id', $sesiId),
                fn ($query) => $query->whereNull('sesi_ujian_cbt_id'),
            )
            ->when(
                $jadwalUjianCbtId,
                fn ($query, $jadwalId) => $query->where('jadwal_ujian_cbt_id', $jadwalId),
                fn ($query) => $query->whereNull('jadwal_ujian_cbt_id'),
            )
            ->exists()) {
            $kode = substr($basis, 0, 34) . '-' . $suffix;
            $suffix++;
        }

        return $kode;
    }

    private function pastikanRuangDanNomorMejaValid($baris, $peserta, $ruang): void
    {
        $pasangan = collect();

        foreach ($baris as $pesertaId => $item) {
            $ruangId = filled($item['ruang_ujian_cbt_id'] ?? null) ? (int) $item['ruang_ujian_cbt_id'] : null;
            $nomorMeja = filled($item['nomor_meja'] ?? null) ? (int) $item['nomor_meja'] : null;
            $pesertaUjian = $peserta[$pesertaId];

            if (! $ruangId && $nomorMeja) {
                throw ValidationException::withMessages([
                    'peserta' => 'Nomor meja hanya dapat diisi jika ruang peserta dipilih.',
                ]);
            }

            if (! $ruangId) {
                continue;
            }

            $ruangUjian = $ruang->get($ruangId);

            if (! $ruangUjian) {
                throw ValidationException::withMessages([
                    'peserta' => 'Ada ruang yang tidak termasuk dalam paket CBT ini.',
                ]);
            }

            if (
                $ruangUjian->sesi_ujian_cbt_id
                && $pesertaUjian->sesi_ujian_cbt_id
                && (int) $ruangUjian->sesi_ujian_cbt_id !== (int) $pesertaUjian->sesi_ujian_cbt_id
            ) {
                throw ValidationException::withMessages([
                    'peserta' => 'Ruang peserta harus sesuai dengan sesi peserta.',
                ]);
            }

            if ($nomorMeja) {
                if ($ruangUjian->kapasitas && $nomorMeja > $ruangUjian->kapasitas) {
                    throw ValidationException::withMessages([
                        'peserta' => 'Nomor meja tidak boleh lebih besar dari kapasitas ruang.',
                    ]);
                }

                $key = "{$ruangId}-{$nomorMeja}";

                if ($pasangan->has($key)) {
                    throw ValidationException::withMessages([
                        'peserta' => 'Ada nomor meja yang dipakai lebih dari satu peserta pada ruang yang sama.',
                    ]);
                }

                $pasangan[$key] = (int) $pesertaId;
            }
        }

        foreach ($pasangan as $key => $pesertaId) {
            [$ruangId, $nomorMeja] = array_map('intval', explode('-', $key));
            $sudahDipakai = PesertaUjianCbt::query()
                ->where('ruang_ujian_cbt_id', $ruangId)
                ->where('nomor_meja', $nomorMeja)
                ->whereNotIn('id', $baris->keys()->map(fn ($id) => (int) $id))
                ->exists();

            if ($sudahDipakai) {
                throw ValidationException::withMessages([
                    'peserta' => 'Ada nomor meja yang sudah dipakai peserta lain.',
                ]);
            }
        }
    }

    private function pastikanPerubahanPesertaTidakMenggangguRuangTerkunci($baris, $peserta, $ruang): void
    {
        $ruangLamaIds = $peserta
            ->pluck('ruang_ujian_cbt_id')
            ->filter()
            ->map(fn ($id) => (int) $id);
        $ruangBaruIds = $baris
            ->pluck('ruang_ujian_cbt_id')
            ->filter()
            ->map(fn ($id) => (int) $id);
        $ruangIds = $ruangLamaIds->merge($ruangBaruIds)->unique()->values();
        $daftarRuang = $ruang
            ->merge(RuangUjianCbt::query()->whereIn('id', $ruangIds->diff($ruang->keys()))->get()->keyBy('id'));

        foreach ($baris as $pesertaId => $item) {
            $pesertaUjian = $peserta[$pesertaId];
            $ruangLamaId = $pesertaUjian->ruang_ujian_cbt_id ? (int) $pesertaUjian->ruang_ujian_cbt_id : null;
            $ruangBaruId = filled($item['ruang_ujian_cbt_id'] ?? null) ? (int) $item['ruang_ujian_cbt_id'] : null;
            $nomorMejaLama = $pesertaUjian->nomor_meja ? (int) $pesertaUjian->nomor_meja : null;
            $nomorMejaBaru = filled($item['nomor_meja'] ?? null) ? (int) $item['nomor_meja'] : null;

            if ($ruangLamaId === $ruangBaruId && $nomorMejaLama === $nomorMejaBaru) {
                continue;
            }

            $ruangLamaTerkunci = $ruangLamaId && $daftarRuang->get($ruangLamaId)?->terkunci();
            $ruangBaruTerkunci = $ruangBaruId && $daftarRuang->get($ruangBaruId)?->terkunci();

            if ($ruangLamaTerkunci || $ruangBaruTerkunci) {
                throw ValidationException::withMessages([
                    'peserta' => 'Penempatan atau nomor meja pada ruang terkunci tidak dapat diubah.',
                ]);
            }
        }
    }

    private function queryRuang(
        UjianCbt $ujianCbt,
        int|string|null $sesiId = null,
        ?int $ruangId = null,
        int|string|null $jadwalId = null,
    ): array
    {
        return array_filter([
            $ujianCbt,
            'sesi_ujian_cbt_id' => $sesiId,
            'jadwal_ujian_cbt_id' => $jadwalId,
            'ruang_ujian_cbt_id' => $ruangId,
        ], fn ($value) => filled($value));
    }

    private function ringkasan(UjianCbt $ujianCbt): array
    {
        $peserta = $ujianCbt->pesertaUjianCbt()
            ->select('status_kehadiran_ujian', DB::raw('count(*) as jumlah'))
            ->groupBy('status_kehadiran_ujian')
            ->pluck('jumlah', 'status_kehadiran_ujian');

        return [
            'ruang' => $ujianCbt->ruangUjianCbt()->count(),
            'peserta' => $ujianCbt->pesertaUjianCbt()->count(),
            'sudah_ditempatkan' => $ujianCbt->pesertaUjianCbt()->whereNotNull('ruang_ujian_cbt_id')->count(),
            'belum_ditempatkan' => $ujianCbt->pesertaUjianCbt()->whereNull('ruang_ujian_cbt_id')->count(),
            'hadir' => (int) ($peserta['hadir'] ?? 0) + (int) ($peserta['terlambat'] ?? 0),
            'belum_absen' => (int) ($peserta['belum_absen'] ?? 0),
            'tidak_hadir' => (int) ($peserta['sakit'] ?? 0) + (int) ($peserta['izin'] ?? 0) + (int) ($peserta['alfa'] ?? 0),
        ];
    }
}
