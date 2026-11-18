@if ($siswa->foto)
    <img class="terpadu-avatar {{ ($kecil ?? false) ? 'is-small' : '' }}" src="{{ asset('storage/'.$siswa->foto) }}" alt="" loading="lazy">
@else
    <span class="terpadu-avatar {{ ($kecil ?? false) ? 'is-small' : '' }}" aria-hidden="true">{{ str($siswa->nama_lengkap)->explode(' ')->filter()->take(2)->map(fn ($kata) => mb_strtoupper(mb_substr($kata, 0, 1)))->join('') }}</span>
@endif
