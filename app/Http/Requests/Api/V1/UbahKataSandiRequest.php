<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UbahKataSandiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kata_sandi_lama' => ['required', 'string'],
            'kata_sandi_baru' => ['required', 'string', 'min:8', 'confirmed', 'different:kata_sandi_lama'],
        ];
    }

    public function messages(): array
    {
        return [
            'kata_sandi_baru.confirmed' => 'Konfirmasi kata sandi baru tidak sama.',
            'kata_sandi_baru.different' => 'Kata sandi baru harus berbeda dari kata sandi lama.',
            'kata_sandi_baru.min' => 'Kata sandi baru minimal 8 karakter.',
        ];
    }
}
