<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Switches the UI language. Signed-in users keep it on their account; guests keep it in a cookie. */
class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', Rule::in(SetLocale::SUPPORTED)]]);

        $user = $request->user();

        if ($user !== null) {
            $user->forceFill(['locale' => $data['locale']])->save();
        }

        // One year. Also covers the login screen, before anyone is signed in.
        return back()->withCookie(cookie('locale', $data['locale'], 60 * 24 * 365));
    }
}
