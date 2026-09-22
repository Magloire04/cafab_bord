<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCoachRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact' => ['nullable', 'string', 'max:50'],
            'date_entree' => ['required', 'date'],
        ];
    }
}
