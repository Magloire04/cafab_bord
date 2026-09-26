<?php

namespace App\Http\Requests\Planning;

use App\Enums\UserRole;
use App\Models\PlanningRepetition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanningRequest extends FormRequest
{
    /**
     * Vérifiée avant la validation : un compte refusé reçoit 403 même avec un formulaire invalide.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', PlanningRepetition::class);
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
