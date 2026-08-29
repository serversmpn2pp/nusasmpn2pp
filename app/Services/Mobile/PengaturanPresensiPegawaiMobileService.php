<?php

namespace App\Services\Mobile;

use App\Models\Pegawai;
use App\Models\PengaturanAbsensiPegawai;
use App\Models\Pengguna;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class PengaturanPresensiPegawaiMobileService
{
    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $kataKunci = trim((string) ($filter['q'] ?? ''));
        $hari = $filter['hari'] ?? 'semua';
        $cakupan = $filter['cakupan'] ?? 'semua_cakupan';
        $status = $filter['status'] ?? 'semua_status';

        $items = PengaturanAbsensiPegawai::query()
            ->with('pegawai:id,nama_lengkap,nip,jenis_pegawai,jabatan_utama')
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci) {
                $query->where(function (Builder $subQuery) use ($kataKunci) {
                    $subQuery
                        ->where('nama_jadwal', 'like', "%{$kataKunci}%")
                        ->orWhere('jenis_pegawai', 'like', "%{$kataKunci}%")
                        ->orWhereHas('pegawai', function (Builder $pegawaiQuery) use ($kataKunci) {
                            $pegawaiQuery
                                ->where('nama_lengkap', 'like', "%{$kataKunci}%")
                                ->orWhere('nip', 'like', "%{$kataKunci}%");
                        });
                });
            })
            ->when($hari !== 'semua', fn (Builder $query) => $query->where('hari', $hari))
            ->when($cakupan !== 'semua_cakupan', fn (Builder $query) => $query->where('cakupan', $cakupan))
            ->when($status === 'aktif', fn (Builder $query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->where('aktif', false))
            ->orderBy('urutan_hari')
            ->orderBy('cakupan')
            ->orderBy('nama_jadwal')
            ->get();

        return [
            'items' => $items->map(fn (PengaturanAbsensiPegawai $item) => $this->ringkas($item))->values(),
            'ringkasan' => [
                'total' => PengaturanAbsensiPegawai::count(),
                'aktif' => PengaturanAbsensiPegawai::where('aktif', true)->count(),
                'nonaktif' => PengaturanAbsensiPegawai::where('aktif', false)->count(),
            ],
            'hari' => collect(PengaturanAbsensiPegawai::DAFTAR_HARI)
                ->map(fn (array $item, string $kode) => [
                    'kode' => $kode,
                    'label' => $item['label'],
                    'urutan' => $item['urutan'],
                ])
                ->values(),
            'cakupan' => collect(PengaturanAbsensiPegawai::DAFTAR_CAKUPAN)
                ->map(fn (string $label, string $kode) => [
                    'kode' => $kode,
                    'label' => $label,
                ])
                ->values(),
            'jenis_pegawai' => collect([
                'Guru',
                'Tenaga Kependidikan',
                'Satpam',
                'Petugas Kebersihan',
            ])
                ->merge(
                    Pegawai::query()
                        ->whereNotNull('jenis_pegawai')
                        ->where('jenis_pegawai', '!=', '')
                        ->distinct()
                        ->pluck('jenis_pegawai'),
                )
                ->unique()
                ->sort()
                ->values(),
            'pegawai' => Pegawai::query()
                ->where('aktif', true)
                ->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap', 'nip', 'jenis_pegawai', 'jabatan_utama'])
                ->map(fn (Pegawai $pegawai) => [
                    'id' => (int) $pegawai->id,
                    'nama' => $pegawai->nama_lengkap,
                    'nip' => $pegawai->nip,
                    'jenis_pegawai' => $pegawai->jenis_pegawai,
                    'jabatan' => $pegawai->jabatan_utama,
                ])
                ->values(),
            'filter' => [
                'q' => $kataKunci,
                'hari' => $hari,
                'cakupan' => $cakupan,
                'status' => $status,
            ],
            'hak_akses' => [
                'dapat_kelola' => $pengguna->memilikiIzin('absensi.pengaturan_kelola'),
            ],
        ];
    }

    public function tambah(array $data): PengaturanAbsensiPegawai
    {
        $data = $this->rapikanData($data);
        $this->pastikanUrutanWaktuBenar($data);
        $this->pastikanSasaranTidakDuplikat($data);

        return PengaturanAbsensiPegawai::create($data);
    }

    public function ubah(PengaturanAbsensiPegawai $pengaturan, array $data): void
    {
        $data = $this->rapikanData($data);
        $this->pastikanUrutanWaktuBenar($data);
        $this->pastikanSasaranTidakDuplikat($data, $pengaturan);
        $pengaturan->update($data);
    }

    private function ringkas(PengaturanAbsensiPegawai $item): array
    {
        return [
            'id' => (int) $item->id,
            'nama_jadwal' => $item->nama_jadwal,
            'cakupan' => $item->cakupan,
            'cakupan_label' => $item->labelCakupan(),
            'jenis_pegawai' => $item->jenis_pegawai,
            'pegawai_id' => $item->pegawai_id ? (int) $item->pegawai_id : null,
            'pegawai' => $item->pegawai ? [
                'id' => (int) $item->pegawai->id,
                'nama' => $item->pegawai->nama_lengkap,
                'nip' => $item->pegawai->nip,
                'jenis_pegawai' => $item->pegawai->jenis_pegawai,
                'jabatan' => $item->pegawai->jabatan_utama,
            ] : null,
            'sasaran_label' => $item->labelSasaran(),
            'hari' => $item->hari,
            'hari_label' => $item->labelHari(),
            'urutan_hari' => (int) $item->urutan_hari,
            'jam_scan_masuk_mulai' => $item->formatJam($item->jam_scan_masuk_mulai),
            'jam_masuk' => $item->formatJam($item->jam_masuk),
            'jam_scan_masuk_selesai' => $item->formatJam($item->jam_scan_masuk_selesai),
            'jam_scan_pulang_mulai' => $item->formatJam($item->jam_scan_pulang_mulai),
            'jam_pulang' => $item->formatJam($item->jam_pulang),
            'jam_scan_pulang_selesai' => $item->formatJam($item->jam_scan_pulang_selesai),
            'aktif' => (bool) $item->aktif,
            'keterangan' => $item->keterangan,
        ];
    }

    private function rapikanData(array $data): array
    {
        $data['nama_jadwal'] = trim($data['nama_jadwal']);
        $data['urutan_hari'] = PengaturanAbsensiPegawai::DAFTAR_HARI[$data['hari']]['urutan'];
        $data['jenis_pegawai'] = $data['cakupan'] === 'jenis_pegawai'
            ? trim((string) ($data['jenis_pegawai'] ?? ''))
            : null;
        $data['pegawai_id'] = $data['cakupan'] === 'pegawai'
            ? (int) $data['pegawai_id']
            : null;
        $data['aktif'] = (bool) $data['aktif'];
        $data['keterangan'] = filled($data['keterangan'] ?? null)
            ? trim($data['keterangan'])
            : null;

        return $data;
    }

    private function pastikanSasaranTidakDuplikat(
        array $data,
        ?PengaturanAbsensiPegawai $pengaturan = null,
    ): void {
        $query = PengaturanAbsensiPegawai::query()
            ->where('hari', $data['hari'])
            ->where('cakupan', $data['cakupan']);

        if ($pengaturan) {
            $query->whereKeyNot($pengaturan->getKey());
        }

        if ($data['cakupan'] === 'jenis_pegawai') {
            $query->where('jenis_pegawai', $data['jenis_pegawai']);
        }

        if ($data['cakupan'] === 'pegawai') {
            $query->where('pegawai_id', $data['pegawai_id']);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'hari' => 'Jadwal untuk sasaran tersebut pada hari yang sama sudah ada. Silakan ubah jadwal yang tersedia.',
            ]);
        }
    }

    private function pastikanUrutanWaktuBenar(array $data): void
    {
        if (! $this->berurutan(
            $data['jam_scan_masuk_mulai'],
            $data['jam_masuk'],
            $data['jam_scan_masuk_selesai'],
        )) {
            throw ValidationException::withMessages([
                'jam_masuk' => 'Jam masuk resmi harus berada di antara waktu mulai dan tutup scan masuk.',
            ]);
        }

        if (! $this->berurutan(
            $data['jam_scan_pulang_mulai'],
            $data['jam_pulang'],
            $data['jam_scan_pulang_selesai'],
        )) {
            throw ValidationException::withMessages([
                'jam_pulang' => 'Jam pulang resmi harus berada di antara waktu mulai dan tutup scan pulang.',
            ]);
        }
    }

    private function berurutan(string $mulai, string $resmi, string $selesai): bool
    {
        return $this->menit($mulai) <= $this->menit($resmi)
            && $this->menit($resmi) <= $this->menit($selesai);
    }

    private function menit(string $jam): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($jam, 0, 5)));

        return ($hour * 60) + $minute;
    }
}
