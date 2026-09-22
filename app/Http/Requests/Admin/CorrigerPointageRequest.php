<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CorrigerPointageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'statut_ponctualite' => ['required', Rule::in(['a_l_heure', 'en_retard', 'retard_fort', 'absent'])],
            'motif' => ['required', 'string', 'min:5'],
        ];
    }
}
