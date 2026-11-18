<section class="tf-surface tf-document-status" aria-labelledby="tf-status-pertemuan">
    <header class="tf-band"><h2 id="tf-status-pertemuan">Status pertemuan</h2><span>{{ $pertemuan->selesai_pada ? 'Selesai' : 'Terbuka' }}</span></header>
    @if ($pertemuan->selesai_pada || ($bolehCatat && $jumlahSetoran > 0))
        <div class="tf-student-row">
            @if ($pertemuan->selesai_pada)
                <p class="person-meta">Dibagikan ke siswa dan orang tua pada {{ $pertemuan->selesai_pada->format('d/m/Y H:i') }}</p>
            @else
                <p class="person-meta">{{ $jumlahSetoran }} setoran siap dibagikan</p>
                <form method="POST" action="{{ route('tahfidz.pertemuan.selesaikan', [$kelompok, $pertemuan]) }}"
                    data-tf-meeting-close onsubmit="return confirm('Selesaikan pertemuan dan bagikan rangkuman ke siswa dan orang tua/wali? Setoran baru tidak dapat ditambahkan setelahnya.')">
                    @csrf
                    <button type="submit" class="button button-primary"><i data-lucide="check" aria-hidden="true"></i> Selesaikan pertemuan</button>
                </form>
            @endif
        </div>
    @endif
</section>
