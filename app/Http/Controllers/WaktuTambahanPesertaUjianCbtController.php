<?php

namespace App\Http\Controllers;

use App\Models\JawabanPesertaUjianCbt;
use App\Models\PesertaUjianCbt;
use App\Models\RiwayatWaktuTambahanUjianCbt;
use App\Models\UjianCbt;
use App\Services\Cbt\BatasWaktuPesertaUjianCbtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WaktuTambahanPesertaUjianCbtController extends Controller
{
    public function update(
        Request $request,
        UjianCbt $ujianCbt,
        PesertaUjianCbt $pesertaUjianCbt,
        BatasWaktuPesertaUjianCbtService $batasWaktu,
    ) {
        abort_unless($ujianCbt->ujianTerpusat(), 404);
        abort_unless((int) $pesertaUjianCbt->ujian_cbt_id === (int) $ujianCbt->id, 404);
        abort_unless($ujianCbt->dapatDiaksesOperasionalOleh($request->user()), 403);

        $data = $request->validate([
            'menit_tambahan' => ['required', 'integer', 'min:5', 'max:120'],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'menit_tambahan.required' => 'Pilih lama waktu tambahan.',
            'menit_tambahan.min' => 'Waktu tambahan minimal 5 menit.',
            'menit_tambahan.max' => 'Waktu tambahan maksimal 120 menit.',
            'alasan.required' => 'Tuliskan alasan pemberian waktu tambahan agar tercatat pada audit.',
            'alasan.min' => 'Alasan waktu tambahan harus lebih jelas.',
        ]);

        if ($ujianCbt->hasil_difinalisasi_pada || $ujianCbt->tampilkan_hasil) {
            throw ValidationException::withMessages([
                'menit_tambahan' => 'Batalkan publikasi dan finalisasi hasil sebelum membuka kembali pengerjaan siswa.',
            ]);
        }

        $nama = $pesertaUjianCbt->anggotaKelas?->siswa?->nama_lengkap ?: 'Peserta';

        $selesaiPada = DB::transaction(function () use ($data, $request, $pesertaUjianCbt, $batasWaktu) {
            $ujian = UjianCbt::query()->lockForUpdate()->findOrFail($pesertaUjianCbt->ujian_cbt_id);
            if ($ujian->hasil_difinalisasi_pada || $ujian->tampilkan_hasil) {
                throw ValidationException::withMessages([
                    'menit_tambahan' => 'Hasil ujian berubah saat diproses. Batalkan publikasi dan finalisasi sebelum membuka kembali pengerjaan siswa.',
                ]);
            }

            $peserta = PesertaUjianCbt::query()
                ->with(['ujianCbt', 'sesiUjianCbt'])
                ->lockForUpdate()
                ->findOrFail($pesertaUjianCbt->id);

            if (! $peserta->waktu_mulai) {
                throw ValidationException::withMessages([
                    'menit_tambahan' => 'Waktu tambahan hanya dapat diberikan kepada siswa yang sudah pernah memulai ujian.',
                ]);
            }

            $batasLama = $batasWaktu->batasAkses($peserta);
            $sudahKedaluwarsa = $batasLama && now()->gte($batasLama);
            $selesaiKarenaWaktu = $peserta->status === 'selesai' && $peserta->cara_selesai === 'waktu_habis';

            if (! $sudahKedaluwarsa && ! $selesaiKarenaWaktu) {
                throw ValidationException::withMessages([
                    'menit_tambahan' => 'Waktu tambahan hanya tersedia setelah waktu siswa habis. Gunakan pembukaan Mode Aman jika siswa masih memiliki waktu.',
                ]);
            }

            if ($peserta->status === 'selesai' && ! $selesaiKarenaWaktu) {
                throw ValidationException::withMessages([
                    'menit_tambahan' => 'Ujian yang dikumpulkan sendiri oleh siswa tidak dapat dibuka kembali melalui waktu tambahan.',
                ]);
            }

            if ($peserta->nilai_siswa_id) {
                throw ValidationException::withMessages([
                    'menit_tambahan' => 'Nilai siswa sudah diterapkan. Batalkan penerapan nilai terlebih dahulu.',
                ]);
            }

            $mulai = now();
            $selesai = $mulai->copy()->addMinutes((int) $data['menit_tambahan']);
            $statusSusulan = in_array($peserta->status_susulan, ['dijadwalkan', 'selesai'], true)
                ? 'dijadwalkan'
                : null;

            $peserta->update([
                'status' => 'sedang_mengerjakan',
                'status_susulan' => $statusSusulan,
                'waktu_selesai' => null,
                'menit_tersisa' => (int) $data['menit_tambahan'],
                'waktu_tambahan_sampai' => $selesai,
                'selesai_otomatis_pada' => null,
                'cara_selesai' => null,
                'alasan_waktu_tambahan' => trim($data['alasan']),
                'waktu_tambahan_diberikan_pada' => $mulai,
                'waktu_tambahan_oleh_pengguna_id' => $request->user()->id,
                'perangkat_terakhir' => null,
                'user_agent_terakhir' => null,
                'status_kehadiran_ujian' => in_array($peserta->status_kehadiran_ujian, [null, 'belum_absen', 'alfa'], true)
                    ? 'hadir'
                    : $peserta->status_kehadiran_ujian,
            ]);

            JawabanPesertaUjianCbt::query()
                ->where('peserta_ujian_cbt_id', $peserta->id)
                ->update(['skor' => null, 'benar' => null]);

            RiwayatWaktuTambahanUjianCbt::create([
                'peserta_ujian_cbt_id' => $peserta->id,
                'menit_tambahan' => (int) $data['menit_tambahan'],
                'mulai_pada' => $mulai,
                'selesai_pada' => $selesai,
                'alasan' => trim($data['alasan']),
                'diberikan_oleh_pengguna_id' => $request->user()->id,
            ]);

            return $selesai;
        });

        return back()->with(
            'berhasil',
            "Waktu tambahan untuk {$nama} aktif sampai pukul {$selesaiPada->format('H:i')}. Jawaban sebelumnya tetap tersimpan; minta siswa memuat ulang halaman ujian.",
        );
    }
}
