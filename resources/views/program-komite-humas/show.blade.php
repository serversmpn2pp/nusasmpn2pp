@extends('layouts.app')
@section('title', 'Program Kerja Komite - NUSA')
@section('program-actions')
    <a class="button button-muted" href="{{ route('komite-humas.program.index', $periode) }}">Daftar program</a>
    @if ($periode->status !== 'arsip' && auth()->user()->memilikiIzin('komite_humas.kelola'))<a class="button button-primary" href="{{ route('komite-humas.program.edit', [$periode, $program]) }}">Edit program</a>@endif
@endsection
@section('content')
<div class="publikasi-page komite-page">
    @include('program-komite-humas._header', ['judulHalaman' => $program->nama])
    <div class="komite-tags"><span class="program-badge program-badge--{{ $program->status }}">{{ \App\Models\ProgramKomiteHumas::STATUS[$program->status] }}</span>@if ($program->terlambat())<span class="program-badge program-badge--terlambat">Lewat target</span>@endif</div>
    <section class="agenda-section"><h2>Rencana & pelaksanaan</h2>@include('program-komite-humas._rincian', ['dataProgram' => $program->snapshot()])@if ($program->penanggungJawab && ! $program->penanggungJawab->aktif)<p class="agenda-muted">Penanggung jawab tercatat sudah tidak aktif sebagai pengurus.</p>@endif</section>
    <section class="agenda-section" id="rapat"><div class="program-section-head"><h2>Rapat terkait</h2>@if ($bolehHubungkan && $program->status !== 'dibatalkan')<a class="button button-primary" href="{{ route('agenda-humas.create', ['program_komite_humas_id' => $program->id]) }}">Buat rapat baru</a>@endif</div>
        @if ($bolehAgenda)
            @forelse ($rapat as $agenda)<article class="program-rapat"><h3><a href="{{ route('agenda-humas.show', $agenda) }}">{{ $agenda->judul }}</a></h3><p class="agenda-muted">{{ $agenda->waktu_mulai->format('d-m-Y H:i') }} WIB &middot; {{ $agenda->labelWaktu() }}</p><p>{{ $agenda->hadir_count }}/{{ $agenda->peserta_count }} peserta hadir &middot; {{ $agenda->tertunda_count }} tindak lanjut belum selesai</p><div class="agenda-actions"><a href="{{ route('agenda-humas.show', [$agenda, 'tab' => 'peserta']) }}">Presensi</a><a href="{{ route('agenda-humas.show', [$agenda, 'tab' => 'notulen']) }}">Notulen & keputusan</a><a href="{{ route('agenda-humas.show', [$agenda, 'tab' => 'tindak-lanjut']) }}">Tindak lanjut</a></div>
                @if ($bolehHubungkan)<details class="program-unlink"><summary>Lepas hubungan rapat</summary><form method="POST" action="{{ route('komite-humas.program.rapat.destroy', [$periode, $program, $agenda]) }}" data-agenda-submit data-confirm-unlink>@csrf @method('DELETE')<input type="hidden" name="versi" value="{{ $program->versi }}"><div class="program-link-form"><div class="field"><label for="alasan_lepas_{{ $agenda->id }}">Alasan pelepasan</label><input id="alasan_lepas_{{ $agenda->id }}" name="catatan_perubahan" class="input" minlength="5" maxlength="2000" required></div><button type="submit" class="button button-muted">Lepas hubungan</button></div></form></details>@endif
            </article>@empty<p class="agenda-muted">Belum ada rapat yang dihubungkan.</p>@endforelse
            {{ $rapat->links() }}
        @else<p class="agenda-muted">Rincian rapat dibatasi sesuai izin Agenda & Pertemuan.</p>@endif
        @if ($pilihanRapat)
            <h3>Hubungkan rapat yang sudah ada</h3><form method="GET" action="{{ route('komite-humas.program.show', [$periode, $program]) }}#rapat" class="agenda-actions"><div class="field"><label for="cari_rapat">Cari rapat komite</label><input id="cari_rapat" name="cari_rapat" class="input" value="{{ $cariRapat }}" maxlength="120"></div><button type="submit" class="button button-muted">Cari</button></form>
            @if ($pilihanRapat->count())<form method="POST" action="{{ route('komite-humas.program.rapat.store', [$periode, $program]) }}" class="program-link-form" data-agenda-submit>@csrf<input type="hidden" name="versi" value="{{ $program->versi }}"><div class="field"><label for="agenda_humas_id">Rapat komite</label><select id="agenda_humas_id" name="agenda_humas_id" class="select" required><option value="">Pilih rapat</option>@foreach ($pilihanRapat as $a)<option value="{{ $a->id }}" @selected((string) old('agenda_humas_id') === (string) $a->id)>{{ $a->waktu_mulai->format('d-m-Y') }} - {{ $a->judul }}</option>@endforeach</select></div><div class="field"><label for="alasan_hubung">Catatan pengaitan</label><input id="alasan_hubung" name="catatan_perubahan" class="input" value="{{ old('catatan_perubahan') }}" minlength="5" maxlength="2000" required></div><button class="button button-primary" type="submit">Hubungkan rapat</button></form>@else<p class="agenda-muted">Tidak ada rapat komite yang cocok dalam masa bakti ini.</p>@endif
            {{ $pilihanRapat->fragment('rapat')->links() }}
        @endif
    </section>
    <section class="agenda-section">
        <h2>Riwayat program</h2>
        <ul class="publikasi-history">
            @foreach ($riwayat as $item)
                <li>
                    <strong>{{ $item->aksi }}</strong>
                    <p class="agenda-muted">{{ $item->pengguna?->nama ?: 'Akun tidak tersedia' }} &middot; {{ $item->created_at->format('d-m-Y H:i') }} &middot; Versi {{ $item->versi + 1 }}</p>
                    @if ($item->catatan_perubahan)<div class="agenda-text">{{ $item->catatan_perubahan }}</div>@endif
                    <details>
                        <summary>Lihat program pada versi ini</summary>
                        <div class="publikasi-snapshot">
                            <h3>{{ $item->snapshot['nama'] }}</h3>
                            @include('program-komite-humas._rincian', ['dataProgram' => $item->snapshot])
                            @if ($bolehAgenda)
                                <h3>Rapat yang terhubung pada versi ini</h3>
                                @forelse ($item->snapshot['rapat'] as $a)
                                    <p>{{ $a['judul'] }} &middot; {{ $a['waktu'] }} &middot; {{ \App\Models\AgendaHumas::STATUS[$a['status']] }}</p>
                                @empty
                                    <p class="agenda-muted">Belum ada rapat.</p>
                                @endforelse
                            @endif
                        </div>
                    </details>
                </li>
            @endforeach
        </ul>
        {{ $riwayat->links() }}
    </section>
</div>
@if ($bolehHubungkan)
    @include('program-komite-humas._scripts')
    @include('agenda-humas._scripts')
@endif
@endsection
