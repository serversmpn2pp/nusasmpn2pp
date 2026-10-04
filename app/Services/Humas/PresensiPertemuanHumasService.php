<?php

namespace App\Services\Humas;

use App\Models\AgendaHumas;
use App\Models\OrangTuaWali;
use App\Models\PesertaPertemuanHumas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PresensiPertemuanHumasService
{
    public function ubahAkses(Request $request, AgendaHumas $agendaHumas)
    {
        $data = $request->validate(['dibuka' => ['required', 'boolean']]);
        DB::transaction(function () use ($agendaHumas, $request, $data) {
            $agenda = AgendaHumas::lockForUpdate()->findOrFail($agendaHumas->id);
            if ($data['dibuka'] && ($agenda->status !== 'terjadwal' || ! $agenda->peserta()->whereNotNull('orang_tua_wali_id')->exists())) {
                throw ValidationException::withMessages(['presensi' => 'Tambahkan undangan orang tua pada agenda terjadwal sebelum membuka presensi QR.']);
            }
            $agenda->forceFill(['presensi_dibuka' => (bool) $data['dibuka'], 'presensi_diubah_pada' => now(),
                'token_presensi' => $agenda->token_presensi ?? Str::random(64), 'diubah_oleh_pengguna_id' => $request->user()->id])->save();
        });

        return $agendaHumas->fresh();
    }

    public function konfirmasi(Request $request, string $token)
    {
        $wali = $this->wali($request);
        DB::transaction(function () use ($request, $wali, $token) {
            $agenda = AgendaHumas::where('token_presensi', $token)->lockForUpdate()->firstOrFail();
            $peserta = $agenda->peserta()->where('orang_tua_wali_id', $wali->id)->lockForUpdate()->first();
            if (! $peserta || ! $this->masihTerhubung($wali, $peserta)) {
                throw ValidationException::withMessages(['presensi' => 'Akun Anda tidak terdaftar dalam undangan ini. Hubungi petugas Humas.']);
            }
            if ($peserta->status_kehadiran === 'hadir') {
                return;
            }
            if (! $agenda->menerimaPresensiQr()) {
                throw ValidationException::withMessages(['presensi' => 'Presensi sudah ditutup atau belum dibuka oleh Humas.']);
            }
            if ($peserta->status_kehadiran !== 'belum_dicatat') {
                throw ValidationException::withMessages(['presensi' => 'Kehadiran Anda sudah dicatat petugas. Hubungi Humas bila perlu koreksi.']);
            }
            $peserta->update(['status_kehadiran' => 'hadir', 'hadir_pada' => now(), 'sumber_kehadiran' => 'qr',
                'dicatat_oleh_pengguna_id' => $request->user()->id, 'versi_presensi' => $peserta->versi_presensi + 1]);
        });

    }

    public function wali(Request $request): OrangTuaWali
    {
        abort_unless($request->user()->aktif && $request->user()->akunOrangTua(), 403, 'Gunakan akun orang tua/wali yang aktif.');

        return $request->user()->orangTuaWali;
    }

    public function masihTerhubung(OrangTuaWali $wali, PesertaPertemuanHumas $peserta): bool
    {
        return $wali->siswa()->whereIn('siswa.id', array_column($peserta->anak_undangan ?? [], 'siswa_id'))->exists();
    }
}
