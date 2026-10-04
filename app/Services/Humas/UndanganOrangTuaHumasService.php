<?php

namespace App\Services\Humas;

use App\Models\AgendaHumas;
use App\Models\AnggotaKelas;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UndanganOrangTuaHumasService
{
    public function pratinjau(array $filter): array
    {
        $anggota = AnggotaKelas::query()->where('tahun_pelajaran_id', $filter['tahun_pelajaran_id'])
            ->where('status_keanggotaan', 'aktif')
            ->whereHas('kelas', fn ($q) => $q->where('aktif', true)->where('tahun_pelajaran_id', $filter['tahun_pelajaran_id'])
                ->when($filter['cakupan'] === 'tingkat', fn ($q) => $q->where('tingkat', $filter['tingkat']))
                ->when($filter['cakupan'] === 'kelas', fn ($q) => $q->whereIn('id', $filter['kelas_ids'])))
            ->whereHas('siswa', fn ($q) => $q->where('aktif', true))
            ->with(['kelas:id,nama', 'siswa:id,nama_lengkap', 'siswa.orangTuaWali' => fn ($q) => $q
                ->whereHas('pengguna', fn ($q) => $q->where('aktif', true)->where('akun_sistem', false)
                    ->whereNull('pegawai_id')->whereNull('siswa_id'))])
            ->get()->unique('siswa_id')->sortBy(fn ($item) => $item->kelas->nama.' '.$item->siswa->nama_lengkap);
        $undangan = collect();
        $tanpaAkun = collect();
        foreach ($anggota as $item) {
            $anak = ['siswa_id' => $item->siswa_id, 'nama' => $item->siswa->nama_lengkap,
                'kelas' => $item->kelas->nama, 'tahun_pelajaran_id' => (int) $filter['tahun_pelajaran_id']];
            if ($item->siswa->orangTuaWali->isEmpty()) {
                $tanpaAkun->push($anak);
            }
            foreach ($item->siswa->orangTuaWali as $wali) {
                $data = $undangan->get($wali->id, ['nama' => $wali->nama_lengkap, 'anak' => []]);
                $data['anak'][] = $anak;
                $undangan->put($wali->id, $data);
            }
        }

        return ['undangan' => $undangan->sortBy('nama'), 'tanpaAkun' => $tanpaAkun, 'jumlahSiswa' => $anggota->count()];
    }

    public function tambahkan(AgendaHumas $agendaHumas, array $filter): array
    {
        return DB::transaction(function () use ($agendaHumas, $filter) {
            $agenda = AgendaHumas::lockForUpdate()->findOrFail($agendaHumas->id);
            if ($agenda->status !== 'terjadwal') {
                throw ValidationException::withMessages(['undangan' => 'Undangan hanya dapat ditambahkan pada agenda terjadwal.']);
            }
            $preview = $this->pratinjau($filter);
            if ($preview['undangan']->isEmpty()) {
                throw ValidationException::withMessages(['undangan' => 'Tidak ada akun orang tua aktif pada pilihan ini. Periksa akun orang tua atau tambahkan peserta secara manual.']);
            }
            $tersimpan = $agenda->peserta()->whereNotNull('orang_tua_wali_id')->get()->keyBy('orang_tua_wali_id');
            $baru = 0;
            foreach ($preview['undangan'] as $waliId => $data) {
                if ($peserta = $tersimpan->get($waliId)) {
                    $anak = collect($peserta->anak_undangan ?? [])->merge($data['anak'])->unique('siswa_id')->values()->all();
                    $peserta->update(['anak_undangan' => $anak]);
                } else {
                    $agenda->peserta()->create(['orang_tua_wali_id' => $waliId, 'nama' => mb_substr($data['nama'], 0, 180),
                        'peran' => 'Orang tua / wali', 'anak_undangan' => $data['anak'],
                        'instansi' => mb_substr(collect($data['anak'])->pluck('kelas')->unique()->join(', '), 0, 180)]);
                    $baru++;
                }
            }

            return ['baru' => $baru, 'sudahAda' => $preview['undangan']->count() - $baru, 'tanpaAkun' => $preview['tanpaAkun']->count()];
        });
    }
}
