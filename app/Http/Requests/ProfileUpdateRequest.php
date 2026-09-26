<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\Email;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Email::normaliser($this->input('email'))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            // L'email sert d'identifiant de connexion : le changer exige le mot de passe actuel.
            'current_password' => [
                Rule::requiredIf(fn () => $this->input('email') !== $this->user()->email),
                'nullable',
                'current_password',
            ],
        ];
    }
}
