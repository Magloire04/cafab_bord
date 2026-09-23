<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePrestationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'lieu' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'montant_defaut' => ['required', 'numeric', 'gt:0'],
            'fille_ids' => ['required', 'array', 'min:1'],
            'fille_ids.*' => ['integer', 'exists:filles,id'],
            'montants' => ['nullable', 'array'],
            'montants.*' => ['nullable', 'numeric', 'gt:0'],
        ];
    }
}
