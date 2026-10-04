@if (session('berhasil')) <div class="alert" role="status">{{ session('berhasil') }}</div> @endif
@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <strong>Ada data yang perlu diperbaiki.</strong>
        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
