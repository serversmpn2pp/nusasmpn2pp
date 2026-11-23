<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SimpanKomponenNilaiKolektifRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'guru_mata_pelajaran_ids' => ['required', 'array', 'list', 'max:100'],
            'guru_mata_pelajaran_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'semester' => ['required', 'in:ganjil,genap'],
            'jenis_komponen' => ['required', 'in:formatif,sumatif,sts,sas_saj'],
            'nama' => ['required', 'string', 'max:255'],
            'tanggal_penilaian' => ['nullable', 'date'],
            'aktif' => ['required', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['guru_mata_pelajaran_ids.required' => 'Pilih minimal satu kelas.'];
    }
}
