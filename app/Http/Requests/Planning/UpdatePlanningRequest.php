<?php

namespace App\Http\Requests\Planning;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // Le coach ne choisit pas : ses créneaux sont toujours à son nom (voir PlanningController).
            'coach_id' => [Rule::requiredIf(fn () => $this->user()->role === UserRole::Admin), 'nullable', 'exists:coaches,id'],
        ];
    }
}
