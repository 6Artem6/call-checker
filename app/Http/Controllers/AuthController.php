<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginForm;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AuthController extends Controller
{
    /**
     * Показать форму входа
     */
    public function showLoginForm()
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Обработка входа пользователя
     */
    public function login(LoginForm $request)
    {
        if ($request->login()) {
            return redirect()->intended('/');
        }

        return redirect()->back()
//            ->withErrors($request->errors())
            ->withInput($request->except('password'));
    }

    /**
     * Выход пользователя
     */
    public function logout()
    {
        Auth::logout();
        session()?->invalidate();
        session()?->regenerateToken();

        return redirect('/');
    }
}
