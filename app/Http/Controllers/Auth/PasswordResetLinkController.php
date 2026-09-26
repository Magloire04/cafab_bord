<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Email;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => Email::normaliser($request->input('email'))]);
        $request->validate(['email' => ['required', 'email']]);

        // Même réponse que l'adresse existe ou non, et même quand l'envoi est
        // limité : la page ne doit pas révéler qui possède un compte.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', __('passwords.sent'));
    }
}
