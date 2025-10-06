<?php

namespace App\Http\Controllers;

use App\Models\AiLead\Account\AccountOAuth2;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OAuth2Controller extends Controller
{
    public function oauth2(Request $request)
    {
        $validated = $request->validate([
            'referer' => ['required', 'string'],
            'code' => ['required', 'string'],
            'client_id' => ['required', 'uuid'],
        ]);

        $domain = $validated['referer'];
        $oauth2_code = $validated['code'];
        $client_id = $validated['client_id'];
        if ($client_id !== env('AMOCRM_CLIENT_ID')) {
            $message = "Не был передан корректный CLIENT_ID.";
        } else {
            $message = AccountOAuth2::retrieveAccessData($domain, $oauth2_code);
        }
        return Inertia::render('OAuth2/Index', [
            'message' => $message,
        ]);
    }
}
