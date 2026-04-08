<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class ForgotPasswordController extends Controller
{
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        // Laravel busca automáticamente el email en la tabla 'users'
        // Si lo encuentra, envía el correo. Si no, devuelve el estado.
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json(['status' => __($status)]);
        }

        // Si el email no existe en la BD, lanza error 422
        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }
}