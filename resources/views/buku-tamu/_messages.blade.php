@if (session('sukses'))<div class="alert" role="status">{{ session('sukses') }}</div>@endif
@if ($errors->any())
    <div class="alert alert-danger" role="alert"><strong>Data belum dapat disimpan.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
