@if ($tautanTerpadu = app(\App\Services\ProfilSiswa\AksesProfilSiswaTerpadu::class)->tautan(auth()->user(), $siswa))
    <a href="{{ $tautanTerpadu }}" class="button button-muted">Lihat profil terpadu</a>
@endif
