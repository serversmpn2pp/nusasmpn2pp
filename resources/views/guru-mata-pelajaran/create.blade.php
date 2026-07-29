@extends('layouts.app')

@section('title', 'Tambah Penugasan Mengajar - NUSA')

@section('content')
    <style>
        .class-selection-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin: 18px 0;
            padding: 14px 0;
            border-top: 1px solid #dce5ee;
            border-bottom: 1px solid #dce5ee;
        }

        .class-selection-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .class-level-stack {
            display: grid;
            gap: 22px;
        }

        .class-level-heading {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .class-level-heading h3 {
            margin: 0;
            color: #15477a;
            font-size: 0.94rem;
        }

        .class-level-heading::after {
            width: 100%;
            height: 1px;
            background: #dce5ee;
            content: "";
        }

        .class-selection-item {
            display: flex;
            align-items: center;
            gap: 11px;
            min-height: 58px;
            padding: 12px 14px;
            border: 1px solid #d7e1ea;
            border-radius: 7px;
            background: #fff;
            cursor: pointer;
        }

        .class-selection-item:has(input:checked) {
            border-color: #15477a;
            background: #eef5fb;
            box-shadow: inset 3px 0 0 #f1c40f;
        }

        .class-selection-item input {
            width: 18px;
            height: 18px;
            flex: 0 0 auto;
            accent-color: #15477a;
        }

        .class-selection-item strong,
        .class-selection-item small {
            display: block;
        }

        .class-selection-item small {
            margin-top: 2px;
            color: #66788a;
        }

        .class-selection-empty {
            padding: 24px 0;
            color: #66788a;
            text-align: center;
        }

        @media (max-width: 880px) {
            .class-selection-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            .class-selection-toolbar {
                align-items: stretch;
                flex-direction: column;
            }

            .class-selection-toolbar .actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }

            .class-selection-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Tambah Penugasan Mengajar</h1>
            <p class="help-text">Pilih guru, mata pelajaran, dan satu atau beberapa kelas yang diajar.</p>
        </div>

        <a href="{{ route('guru-mata-pelajaran.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('guru-mata-pelajaran.store') }}" method="POST">
        @csrf
        @include('guru-mata-pelajaran.partials.form-massal')
    </form>
@endsection
