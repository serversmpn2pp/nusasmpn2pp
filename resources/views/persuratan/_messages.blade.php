@if(session('berhasil'))<x-ui.alert type="success">{{ session('berhasil') }}</x-ui.alert>@endif
@if($errors->any())<x-ui.alert type="danger" title="Ada data yang perlu diperbaiki."><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.alert>@endif
