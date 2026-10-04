<?php

namespace App\Services\Humas;

use App\Models\AgendaHumas;
use App\Models\PeriodeKomiteHumas;
use App\Models\ProgramKomiteHumas;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class KelolaProgramKomiteHumasService
{
    public function kunciPeriode(int $id): PeriodeKomiteHumas
    {
        app(KelolaKomiteHumasService::class)->kunciPeriode();
        $periode = PeriodeKomiteHumas::lockForUpdate()->findOrFail($id);
        if ($periode->status === 'arsip') {
            throw ValidationException::withMessages(['periode' => 'Kepengurusan diarsipkan. Program dan hubungan rapatnya hanya dapat dilihat.']);
        }

        return $periode;
    }

    public function validasiProgram(PeriodeKomiteHumas $periode, ProgramKomiteHumas $program): void
    {
        if ($program->tanggal_mulai->lt($periode->tanggal_mulai) || $program->tanggal_selesai->gt($periode->tanggal_selesai)) {
            throw ValidationException::withMessages(['tanggal_mulai' => 'Jadwal program harus berada dalam masa bakti kepengurusan.']);
        }
        if ($periode->status === 'draf' && $program->status !== 'rencana') {
            throw ValidationException::withMessages(['status' => 'Aktifkan kepengurusan terlebih dahulu sebelum menjalankan program.']);
        }
        if ($program->pengurus_komite_humas_id) {
            $pj = $periode->pengurus()->whereKey($program->pengurus_komite_humas_id)->first(['id', 'aktif']);
            if (! $pj || (! $pj->aktif && (! $program->exists || $program->isDirty('pengurus_komite_humas_id')))) {
                throw ValidationException::withMessages(['pengurus_komite_humas_id' => 'Pilih pengurus aktif dari kepengurusan ini.']);
            }
        }
    }

    public function validasiRapat(PeriodeKomiteHumas $periode, AgendaHumas $agenda, bool $baru = true): void
    {
        if ($agenda->jenis !== 'komite') {
            throw ValidationException::withMessages(['agenda_humas_id' => 'Pilih agenda berjenis Rapat komite sekolah.']);
        }
        if ($agenda->waktu_mulai->copy()->startOfDay()->lt($periode->tanggal_mulai) || $agenda->waktu_selesai->copy()->startOfDay()->gt($periode->tanggal_selesai)) {
            throw ValidationException::withMessages(['waktu_mulai' => 'Jadwal rapat harus berada dalam masa bakti kepengurusan terkait.']);
        }
        if ($baru && $agenda->status === 'dibatalkan') {
            throw ValidationException::withMessages(['agenda_humas_id' => 'Rapat yang dibatalkan tidak dapat ditambahkan.']);
        }
    }

    public function pastikanJadwalPeriode(PeriodeKomiteHumas $periode): void
    {
        if (! $periode->exists || ! $periode->isDirty(['tanggal_mulai', 'tanggal_selesai'])) {
            return;
        }
        if ($periode->program()->where(fn ($q) => $q->whereDate('tanggal_mulai', '<', $periode->tanggal_mulai)->orWhereDate('tanggal_selesai', '>', $periode->tanggal_selesai))->exists()) {
            throw ValidationException::withMessages(['tanggal_mulai' => 'Masa bakti baru tidak mencakup jadwal program yang sudah tersimpan. Sesuaikan jadwal program terlebih dahulu.']);
        }
        if (AgendaHumas::whereHas('programKomite', fn ($q) => $q->where('periode_komite_humas_id', $periode->id))
            ->where(fn ($q) => $q->whereDate('waktu_mulai', '<', $periode->tanggal_mulai)->orWhereDate('waktu_selesai', '>', $periode->tanggal_selesai))->exists()) {
            throw ValidationException::withMessages(['tanggal_mulai' => 'Masa bakti baru tidak mencakup rapat komite yang telah dihubungkan.']);
        }
    }

    public function validasiPerubahanAgenda(AgendaHumas $agenda): void
    {
        foreach (PeriodeKomiteHumas::whereIn('id', $agenda->programKomite()->select('periode_komite_humas_id'))->get() as $periode) {
            $this->validasiRapat($periode, $agenda, false);
        }
    }

    public function hubungkan(ProgramKomiteHumas $program, AgendaHumas $agenda, Request $request, string $catatan): void
    {
        $this->validasiRapat($program->periode, $agenda);
        if ($program->status === 'dibatalkan') {
            throw ValidationException::withMessages(['status' => 'Program dibatalkan. Ubah status program sebelum menghubungkan rapat.']);
        }
        if (! $program->agenda()->whereKey($agenda->id)->exists()) {
            $program->agenda()->attach($agenda);
            $program->forceFill(['versi' => $program->versi + 1, 'diubah_oleh_pengguna_id' => $request->user()->id])->save();
            $this->catat($program, $request, 'Rapat dihubungkan', $catatan);
        }
    }

    public function catat(ProgramKomiteHumas $program, Request $request, string $aksi, ?string $catatan = null): void
    {
        $program->riwayat()->create(['versi' => $program->versi, 'aksi' => $aksi, 'snapshot' => $program->snapshot(),
            'catatan_perubahan' => $catatan, 'pengguna_id' => $request->user()->id, 'created_at' => now()]);
    }
}
