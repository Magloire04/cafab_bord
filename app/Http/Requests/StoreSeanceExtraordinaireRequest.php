<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSeanceExtraordinaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'coach_id' => ['required', 'exists:coaches,id'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'heure_prevue' => ['required', 'date_format:H:i'],
        ];
    }
}
