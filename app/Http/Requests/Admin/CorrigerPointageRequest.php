<?php

namespace App\Http\Requests\Admin;

use App\Enums\StatutPonctualite;
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
            'statut_ponctualite' => ['required', Rule::enum(StatutPonctualite::class)],
            'minutes_retard' => ['nullable', 'integer', 'min:0'],
            'motif' => ['required', 'string', 'min:5'],
        ];
    }
}
