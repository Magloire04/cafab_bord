<?php

namespace App\Http\Requests\Admin;

use App\Support\Email;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCoachRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Email::normaliser($this->input('email'))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('coach')->user_id)],
            'contact' => ['nullable', 'string', 'max:50'],
            'date_entree' => ['required', 'date'],
        ];
    }
}
