<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSeanceExtraordinaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Le coach ne choisit pas : ses séances sont toujours à son nom (voir SeanceController).
            'coach_id' => [Rule::requiredIf(fn () => $this->user()->role === UserRole::Admin), 'nullable', 'exists:coaches,id'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'heure_prevue' => ['required', 'date_format:H:i'],
        ];
    }
}
