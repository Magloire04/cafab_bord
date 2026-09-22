<?php

namespace App\Http\Requests\Coach;

use App\Models\Coach;
use App\Models\Fille;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarquerPresenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'seance_id' => ['required', 'exists:seances,id'],
            'pointable_type' => ['required', Rule::in([Coach::class, Fille::class])],
            'pointable_id' => ['required', 'integer'],
            'heure' => ['nullable', 'date_format:H:i'],
        ];
    }
}
