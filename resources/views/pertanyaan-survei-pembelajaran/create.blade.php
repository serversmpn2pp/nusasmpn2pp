@extends('layouts.app')

@section('title', 'Tambah Pernyataan Survei - NUSA')

@section('content')
    <style>
        .survey-statement-shell{display:grid;gap:18px;margin:0 auto;max-width:820px}
        .survey-statement-form{display:grid;gap:18px}
        .survey-statement-order{max-width:190px}
        .survey-statement-check{align-items:center;color:#172536;display:flex;font-size:.82rem;font-weight:800;gap:9px;width:max-content}
        .survey-statement-check input{accent-color:#15477a;height:18px;width:18px}
        .survey-statement-form-actions{justify-content:flex-end}
        @media(max-width:620px){.survey-statement-order{max-width:none}.survey-statement-form-actions{display:grid;grid-template-columns:1fr 1fr}.survey-statement-form-actions .button{justify-content:center;width:100%}}
    </style>

    <div class="survey-statement-shell">
        <div class="page-header">
            <div>
                <p class="eyebrow">Kurikulum</p>
                <h1 class="page-title">Tambah pernyataan survei</h1>
            </div>
        </div>

        <form method="POST" action="{{ route('pertanyaan-survei-pembelajaran.store') }}">
            @csrf
            @include('pertanyaan-survei-pembelajaran._form', ['tombolSimpan' => 'Tambah pernyataan'])
        </form>
    </div>
@endsection
