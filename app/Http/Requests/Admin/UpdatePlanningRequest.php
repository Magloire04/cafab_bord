<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlanningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'jour_semaine' => ['required', 'integer', 'between:1,7'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'coach_id' => ['required', 'exists:coaches,id'],
        ];
    }
}
