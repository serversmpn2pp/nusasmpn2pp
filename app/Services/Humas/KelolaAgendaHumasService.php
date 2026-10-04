<?php

namespace App\Services\Humas;

use App\Models\AgendaHumas;
use App\Models\DokumenHumas;
use App\Models\MitraHumas;
use App\Models\PesertaPertemuanHumas;
use App\Models\ProgramKomiteHumas;
use App\Models\TindakLanjutAgendaHumas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KelolaAgendaHumasService
{
    public function store(Request $request)
    {
        $data = $request->validate($this->aturanAgenda() + ['mitra_humas_id' => ['nullable', 'integer', 'exists:mitra_humas,id', 'prohibits:program_komite_humas_id'],
            'program_komite_humas_id' => ['nullable', 'integer', 'exists:program_komite_humas,id']]);
        $programId = $data['program_komite_humas_id'] ?? null;
        $this->programUntukRapat($request, $programId);
        $mitraId = $data['mitra_humas_id'] ?? null;
        if ($mitraId) {
            abort_unless($request->user()->memilikiIzin('kemitraan_humas.kelola'), 403);
        }
        unset($data['mitra_humas_id'], $data['program_komite_humas_id']);
        $agenda = DB::transaction(function () use ($request, $data, $mitraId, $programId) {
            $program = null;
            if ($programId) {
                $periodeId = ProgramKomiteHumas::findOrFail($programId)->periode_komite_humas_id;
                app(KelolaProgramKomiteHumasService::class)->kunciPeriode($periodeId);
                $program = ProgramKomiteHumas::lockForUpdate()->findOrFail($programId);
            }
            $mitra = $mitraId ? MitraHumas::lockForUpdate()->findOrFail($mitraId) : null;
            if ($mitra) {
                app(KelolaKemitraanHumasService::class)->pastikanMitraAktif($mitra);
            }
            $agenda = AgendaHumas::create($data + [
                'status' => 'terjadwal', 'dibuat_oleh_pengguna_id' => $request->user()->id,
                'diubah_oleh_pengguna_id' => $request->user()->id,
            ]);
            if ($program) {
                app(KelolaProgramKomiteHumasService::class)->hubungkan($program, $agenda, $request, 'Rapat dibuat dari program kerja komite.');
            }
            if ($mitra) {
                $mitra->agenda()->attach($agenda);
                app(KelolaKemitraanHumasService::class)->catat($mitra, null, 'Agenda dibuat', null, ['agenda' => $agenda->judul, 'agenda_humas_id' => $agenda->id], $request->user());
            }

            return $agenda;
        });

        return $agenda->fresh();
    }

    public function update(Request $request, AgendaHumas $agendaHumas)
    {
        $data = $request->validate($this->aturanAgenda());
        DB::transaction(function () use ($request, $agendaHumas, $data) {
            // Match the lock order used when programs link or unlink meetings.
            app(KelolaKomiteHumasService::class)->kunciPeriode();
            $agenda = AgendaHumas::lockForUpdate()->findOrFail($agendaHumas->id);
            $agenda->fill($data);
            app(KelolaProgramKomiteHumasService::class)->validasiPerubahanAgenda($agenda);
            $agenda->fill(['diubah_oleh_pengguna_id' => $request->user()->id])->save();
        });

        return $agendaHumas->fresh();
    }

    public function simpanNotulen(Request $request, AgendaHumas $agendaHumas)
    {
        $data = $request->validate([
            'pembahasan' => ['required', 'string', 'max:20000'],
            'keputusan' => ['required', 'string', 'max:20000'],
        ]);
        DB::transaction(function () use ($request, $agendaHumas, $data) {
            $agenda = $this->kunciAgendaAktif($agendaHumas);
            $agenda->update($data + ['diubah_oleh_pengguna_id' => $request->user()->id]);
        });

        return $agendaHumas->fresh();
    }

    public function ubahStatus(Request $request, AgendaHumas $agendaHumas)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(AgendaHumas::STATUS))],
            'alasan_pembatalan' => ['nullable', 'required_if:status,dibatalkan', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($request, $agendaHumas, $data) {
            $agenda = AgendaHumas::lockForUpdate()->findOrFail($agendaHumas->id);
            if ($data['status'] === 'selesai' && (blank($agenda->pembahasan) || blank($agenda->keputusan))) {
                throw ValidationException::withMessages(['status' => 'Simpan pembahasan dan keputusan pada tab Notulen sebelum menandai pertemuan selesai.']);
            }
            $agenda->update([
                'status' => $data['status'],
                'alasan_pembatalan' => $data['status'] === 'dibatalkan' ? $data['alasan_pembatalan'] : null,
                'diselesaikan_pada' => $data['status'] === 'selesai' ? ($agenda->diselesaikan_pada ?? now()) : null,
                'diubah_oleh_pengguna_id' => $request->user()->id,
            ]);
            if ($data['status'] !== 'terjadwal') {
                $agenda->forceFill(['presensi_dibuka' => false, 'presensi_diubah_pada' => now()])->save();
            }
        });

        return $agendaHumas->fresh();
    }

    public function tambahPeserta(Request $request, AgendaHumas $agendaHumas)
    {
        $data = $request->validate([
            'peserta' => ['required', 'array', 'min:1', 'max:100'],
            'peserta.*' => ['array:nama,instansi,peran'],
            'peserta.*.nama' => ['required', 'string', 'max:180'],
            'peserta.*.instansi' => ['nullable', 'string', 'max:180'],
            'peserta.*.peran' => ['nullable', 'string', 'max:120'],
        ]);
        DB::transaction(function () use ($agendaHumas, $data) {
            $agenda = $this->kunciAgendaAktif($agendaHumas);
            $agenda->peserta()->createMany($data['peserta']);
        });

        return $agendaHumas->fresh();
    }

    public function simpanPresensi(Request $request, AgendaHumas $agendaHumas)
    {
        $data = $request->validate([
            'jumlah_baris' => ['required', 'integer', 'min:1', 'max:50'],
            'kehadiran' => ['required', 'array', 'min:1', 'max:50'],
            'kehadiran.*' => ['array:id,nama,instansi,peran,status_kehadiran,catatan,versi_presensi'],
            'kehadiran.*.id' => ['required', 'integer', 'distinct'],
            'kehadiran.*.versi_presensi' => ['required', 'integer', 'min:0'],
            'kehadiran.*.nama' => ['sometimes', 'required', 'string', 'max:180'],
            'kehadiran.*.instansi' => ['sometimes', 'nullable', 'string', 'max:180'],
            'kehadiran.*.peran' => ['sometimes', 'nullable', 'string', 'max:120'],
            'kehadiran.*.status_kehadiran' => ['required', Rule::in(array_keys(PesertaPertemuanHumas::KEHADIRAN))],
            'kehadiran.*.catatan' => ['nullable', 'string', 'max:500'],
        ], ['kehadiran.*.versi_presensi.required' => 'Formulir presensi perlu dimuat ulang sebelum disimpan.']);
        DB::transaction(function () use ($request, $agendaHumas, $data) {
            $agenda = $this->kunciAgendaAktif($agendaHumas);
            if (count($data['kehadiran']) !== (int) $data['jumlah_baris']) {
                throw ValidationException::withMessages(['kehadiran' => 'Data formulir tidak lengkap. Muat ulang daftar sebelum menyimpan.']);
            }
            $peserta = $agenda->peserta()->whereIn('id', array_column($data['kehadiran'], 'id'))->lockForUpdate()->get()->keyBy('id');
            if ($peserta->count() !== count($data['kehadiran'])) {
                throw ValidationException::withMessages(['kehadiran' => 'Ada peserta yang bukan bagian dari agenda ini. Muat ulang daftar peserta.']);
            }
            foreach ($data['kehadiran'] as $baris) {
                $item = $peserta->get($baris['id']);
                if ($item->versi_presensi !== (int) $baris['versi_presensi']) {
                    throw ValidationException::withMessages(['kehadiran' => 'Kehadiran '.$item->nama.' baru saja berubah. Muat ulang daftar agar presensi terbaru tidak tertimpa.']);
                }
                $statusSebelum = $item->status_kehadiran;
                $item->fill(['status_kehadiran' => $baris['status_kehadiran'], 'catatan' => $baris['catatan'] ?? null]
                    + array_intersect_key($baris, array_flip(['nama', 'instansi', 'peran'])));
                if (! $item->isDirty()) {
                    continue;
                }
                $item->fill([
                    'hadir_pada' => $baris['status_kehadiran'] === 'hadir' ? ($item->hadir_pada ?? now()) : null,
                    'dicatat_oleh_pengguna_id' => $request->user()->id,
                    'versi_presensi' => $item->versi_presensi + 1,
                    'sumber_kehadiran' => $baris['status_kehadiran'] === 'belum_dicatat' ? null
                        : ($baris['status_kehadiran'] === $statusSebelum ? ($item->sumber_kehadiran ?? 'manual') : 'manual'),
                ])->save();
            }
        });

        return $agendaHumas->fresh();
    }

    public function hapusPeserta(AgendaHumas $agendaHumas, PesertaPertemuanHumas $pesertaPertemuanHumas)
    {
        abort_unless($pesertaPertemuanHumas->agenda_humas_id === $agendaHumas->id, 404);
        DB::transaction(function () use ($agendaHumas, $pesertaPertemuanHumas) {
            $this->kunciAgendaAktif($agendaHumas);
            $peserta = PesertaPertemuanHumas::lockForUpdate()->findOrFail($pesertaPertemuanHumas->id);
            if ($peserta->status_kehadiran !== 'belum_dicatat') {
                throw ValidationException::withMessages(['peserta' => 'Peserta yang sudah memiliki catatan kehadiran tidak dapat dihapus.']);
            }
            $peserta->delete();
        });

        return $agendaHumas->fresh();
    }

    public function tambahTindakLanjut(Request $request, AgendaHumas $agendaHumas)
    {
        $data = $request->validate($this->aturanTindakLanjut());
        DB::transaction(function () use ($request, $agendaHumas, $data) {
            $agenda = $this->kunciAgendaAktif($agendaHumas);
            $agenda->tindakLanjut()->create($data + ['diubah_oleh_pengguna_id' => $request->user()->id]);
        });

        return $agendaHumas->fresh();
    }

    public function perbaruiTindakLanjut(Request $request, AgendaHumas $agendaHumas, TindakLanjutAgendaHumas $tindakLanjutAgendaHumas)
    {
        abort_unless($tindakLanjutAgendaHumas->agenda_humas_id === $agendaHumas->id, 404);
        $data = $request->validate($this->aturanTindakLanjut() + [
            'status' => ['required', Rule::in(array_keys(TindakLanjutAgendaHumas::STATUS))],
            'catatan' => ['nullable', 'required_if:status,selesai', 'string', 'max:5000'],
        ]);
        DB::transaction(function () use ($request, $agendaHumas, $tindakLanjutAgendaHumas, $data) {
            $this->kunciAgendaAktif($agendaHumas);
            $item = TindakLanjutAgendaHumas::lockForUpdate()->findOrFail($tindakLanjutAgendaHumas->id);
            $item->update($data + [
                'selesai_pada' => $data['status'] === 'selesai' ? ($item->selesai_pada ?? now()) : null,
                'diubah_oleh_pengguna_id' => $request->user()->id,
            ]);
        });

        return $agendaHumas->fresh();
    }

    public function hubungkanDokumen(Request $request, AgendaHumas $agendaHumas)
    {
        $data = $request->validate(['dokumen_humas_id' => ['required', 'integer', 'exists:dokumen_humas,id']]);
        DB::transaction(function () use ($agendaHumas, $data) {
            $agenda = $this->kunciAgendaAktif($agendaHumas);
            $agenda->dokumen()->syncWithoutDetaching([$data['dokumen_humas_id']]);
        });

        return $agendaHumas->fresh();
    }

    public function lepasDokumen(AgendaHumas $agendaHumas, DokumenHumas $dokumenHumas)
    {
        DB::transaction(function () use ($agendaHumas, $dokumenHumas) {
            $agenda = $this->kunciAgendaAktif($agendaHumas);
            abort_unless($agenda->dokumen()->where('dokumen_humas.id', $dokumenHumas->id)->exists(), 404);
            $agenda->dokumen()->detach($dokumenHumas->id);
        });

        return $agendaHumas->fresh();
    }

    private function aturanAgenda(): array
    {
        return [
            'judul' => ['required', 'string', 'max:180'],
            'jenis' => ['required', Rule::in(array_keys(AgendaHumas::JENIS))],
            'waktu_mulai' => ['required', 'date_format:Y-m-d\TH:i'],
            'waktu_selesai' => ['required', 'date_format:Y-m-d\TH:i', 'after:waktu_mulai'],
            'tempat' => ['required', 'string', 'max:180'],
            'tautan_pertemuan' => ['nullable', 'url:http,https', 'max:1000'],
            'sasaran' => ['nullable', 'string', 'max:250'],
            'pemimpin' => ['nullable', 'string', 'max:180'],
            'notulis' => ['nullable', 'string', 'max:180'],
            'topik' => ['required', 'string', 'max:10000'],
        ];
    }

    private function programUntukRapat(Request $request, ?int $id): ?ProgramKomiteHumas
    {
        if (! $id) {
            return null;
        }
        abort_unless($request->user()->memilikiIzin('komite_humas.kelola') && $request->user()->aktif && ! $request->user()->akunOrangTua() && ! $request->user()->akunSiswa(), 403);
        $program = ProgramKomiteHumas::with('periode')->findOrFail($id);
        if ($program->periode->status === 'arsip' || $program->status === 'dibatalkan') {
            throw ValidationException::withMessages(['program_komite_humas_id' => 'Program dibatalkan atau kepengurusan diarsipkan. Rapat baru tidak dapat ditambahkan.']);
        }

        return $program;
    }

    private function aturanTindakLanjut(): array
    {
        return ['uraian' => ['required', 'string', 'max:5000'],
            'penanggung_jawab' => ['required', 'string', 'max:180'],
            'batas_tanggal' => ['nullable', 'date_format:Y-m-d']];
    }

    private function kunciAgendaAktif(AgendaHumas $agendaHumas): AgendaHumas
    {
        $agenda = AgendaHumas::lockForUpdate()->findOrFail($agendaHumas->id);
        if ($agenda->status === 'dibatalkan') {
            throw ValidationException::withMessages(['agenda' => 'Agenda dibatalkan. Jadwalkan kembali sebelum memperbarui data pertemuan.']);
        }

        return $agenda;
    }
}
