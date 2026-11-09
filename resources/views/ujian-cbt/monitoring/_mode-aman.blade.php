@if ($peserta->status === 'terblokir')
    <div style="margin-top: 10px;">
        @if ($peserta->jumlah_pindah_aplikasi > 0)
            <p class="person-meta">{{ $peserta->jumlah_pindah_aplikasi }} kejadian keluar aplikasi</p>
        @endif
        @if ($peserta->dapat_dibuka_mode_aman)
            <details class="monitor-device-reset" data-monitor-action>
                <summary class="button button-primary"><i data-lucide="lock-open" aria-hidden="true"></i> Buka Ujian</summary>
                <form method="POST" action="{{ route('ujian-cbt.monitoring.buka-mode-aman', [$ujianCbt, $peserta]) }}"
                    class="monitor-device-reset-form" data-unlock-participant="{{ $peserta->id }}"
                    onsubmit="return confirm('Buka kembali ujian peserta ini setelah pemeriksaan? Jawaban dan catatan kejadian tetap disimpan; waktu ujian tidak ditambah.')">
                    @csrf
                    <label class="field">
                        <span>Alasan pembukaan</span>
                        <textarea class="input" name="alasan_pembukaan" rows="3" minlength="10" maxlength="500" required
                            placeholder="Jelaskan hasil pemeriksaan dan alasan siswa diizinkan melanjutkan."></textarea>
                    </label>
                    <p class="person-meta">Wajib 10–500 karakter. Alasan dan identitas petugas dicatat. Waktu ujian tetap berjalan.</p>
                    <button type="submit" class="button button-primary">Konfirmasi Buka Ujian</button>
                </form>
            </details>
            <p class="person-meta" style="margin-top: 6px;">Setelah dibuka, siswa menekan Periksa Status Ujian.</p>
        @else
            <p class="person-meta">Pembukaan sesi memerlukan guru atau petugas yang berwenang.</p>
        @endif
    </div>
@endif

@if ($peserta->dapat_reset_perangkat)
    <details class="monitor-device-reset" data-monitor-action style="margin-top: 10px;">
        <summary class="button button-muted"><i data-lucide="monitor-cog" aria-hidden="true"></i> Reset perangkat</summary>
        <form method="POST" action="{{ route('keamanan-ujian.reset-perangkat', $peserta) }}"
            class="monitor-device-reset-form" data-reset-participant="{{ $peserta->id }}"
            onsubmit="return confirm('Reset ikatan perangkat peserta ini? Pastikan sesi lama sudah ditutup. Jawaban dan riwayat tetap disimpan; waktu tidak ditambah dan tahanan Mode Aman tetap berlaku.')">
            @csrf
            <p class="person-meta">Gunakan jika siswa perlu mengganti perangkat. Pastikan sesi lama sudah ditutup. Reset tidak membuka tahanan Mode Aman.</p>
            <label class="field">
                <span>Alasan penggantian perangkat</span>
                <textarea class="input" name="alasan" rows="3" minlength="10" maxlength="500" required
                    placeholder="Contoh: HP siswa rusak, pindah ke perangkat pengganti."></textarea>
            </label>
            <button type="submit" class="button button-muted">Konfirmasi reset perangkat</button>
        </form>
    </details>
@endif
