<?php

namespace App\Http\Controllers;

use App\Models\AccountOAuth2;
use App\Models\AccountUser;
use App\Models\ChatGPTSetting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Inertia\Inertia;
use Laravel\Passport\Token;

class OIDCController extends Controller
{
    private const JWT_TTL = 2592000; // 30 дней

    /**
     * Запрос токенов у OAuth2 сервера
     * @param array $params
     * @return array
     */
    private function requestOAuthToken(array $params): array
    {
        $resp = Http::asForm()->post(config('app.url') . '/oauth/token', $params);
        if ($resp->failed()) {
            abort(401, 'OAuth token error');
        }
        return $resp->json();
    }

    private function encodeJwt(array $payload): string
    {
        $payload['exp'] = time() + static::JWT_TTL;
        return JWT::encode($payload, $this->getPrivateKey(), 'RS256');
    }

    private function getPrivateKey(): string
    {
        return file_get_contents(storage_path(config('jwt.private_key_path')));
    }

    private function getPublicKey(): string
    {
        return file_get_contents(storage_path(config('jwt.public_key_path')));
    }

    public function registerForm(Request $request)
    {
        return Inertia::render('OIDC/Register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:account_user,email'],
            'password' => ['required', 'min:8'],
            'domain' => ['required', 'string', 'max:255'],
            'account_id' => ['required', 'integer'],
        ]);

        $user = AccountUser::firstOrCreate(
            ['email' => $data['email']],
            ['password' => Hash::make($data['password'])]
        );

        AccountOAuth2::updateOrInsert([
            'account_id' => $data['account_id']
        ], [
            'domain' => $data['domain'],
            'user_id' => $user->user_id,
        ]);

        ChatGPTSetting::firstOrCreate(['account_id' => $data['account_id']]);

        $tokenData = $this->requestOAuthToken([
            'grant_type' => 'password',
            'client_id' => config('passport.password_client_id'),
            'client_secret' => config('passport.password_client_secret'),
            'username' => $data['email'],
            'password' => $data['password'],
            'scope' => '',
        ]);

        $user->update([
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'],
            'expires_in' => date('Y-m-d H:i:s', time() + $tokenData['expires_in']),
        ]);

        $jwt = $this->encodeJwt(['account_id' => $data['account_id']]);
        return $this->buildTokenResponse($tokenData, $jwt);
    }

    public function loginForm(Request $request)
    {
        return Inertia::render('OIDC/Login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $tokenData = $this->requestOAuthToken([
            'grant_type' => 'password',
            'client_id' => config('passport.password_client_id'),
            'client_secret' => config('passport.password_client_secret'),
            'username' => $data['email'],
            'password' => $data['password'],
            'scope' => '',
        ]);

        $user = AccountUser::where('email', $data['email'])->firstOrFail();
        $accountId = $user->oauth2->account_id;

        $user->update([
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'],
            'expires_in' => date('Y-m-d H:i:s', time() + $tokenData['expires_in']),
        ]);

        $jwt = $this->encodeJwt(['account_id' => $accountId]);
        return $this->buildTokenResponse($tokenData, $jwt);
    }

    public function logout(Request $request)
    {
        $t = $request->bearerToken() ??
             $request->cookie(config('session.cookie_token')) ??
             $request->cookie('oidc_jwt');

        if ($t) {
            $parts = explode('.', $t);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
                $jti = $payload['jti'] ?? null;

                if ($jti) {
                    $token = Token::where('id', $jti)->first();
                    if ($token && !$token->revoked) {
                        $token->revoke();
                    }
                }
            }
            // Можно ещё чистить access/refresh токены из AccountUser
            AccountUser::where('access_token', $t)->update([
                'access_token' => null,
                'refresh_token' => null,
                'expires_in' => null,
            ]);
        }

        return response()->json(['message' => 'Выход выполнен']);
    }

    public function callback(Request $request)
    {
        $request->validate(['code' => ['required', 'string']]);
        $verifier = $request->session()->pull('code_verifier');
        if (!$verifier) {
            abort(400, 'PKCE verifier missing');
        }

        $tokenData = $this->requestOAuthToken([
            'grant_type' => 'authorization_code',
            'client_id' => env('PUBLIC_CLIENT_ID'),
            'redirect_uri' => route('auth-callback'),
            'code' => $request->input('code'),
            'code_verifier' => $verifier,
        ]);

        $accountId = $request->user()->oauth2->account_id;
        $request->user()->update(['refresh_token' => $tokenData['refresh_token']]);

        $jwt = $this->encodeJwt(['account_id' => $accountId]);
        return $this->buildTokenResponse($tokenData, $jwt);
    }

    public function refreshToken(Request $request)
    {
        $jwtToken = $request->bearerToken();
        if (!$jwtToken) {
            abort(401);
        }
        try {
            $payload = JWT::decode($jwtToken, new Key($this->getPublicKey(), 'RS256'));
        } catch (ExpiredException $e) {
            abort(401, 'Token expired');
        } catch (SignatureInvalidException $e) {
            abort(401, 'Invalid signature');
        } catch (\Exception $e) {
            abort(401, 'Invalid token');
        }

        $accountId = $payload->account_id;
        $oauth = AccountOAuth2::where('account_id', $accountId)->firstOrFail();
        $user = AccountUser::where('user_id', $oauth->user_id)->firstOrFail();

        $tokenData = $this->requestOAuthToken([
            'grant_type' => 'refresh_token',
            'refresh_token' => $user->refresh_token,
            'client_id' => config('passport.password_client_id'),
            'client_secret' => config('passport.password_client_secret'),
            'scope' => '',
        ]);

        $user->update(['refresh_token' => $tokenData['refresh_token']]);
        $newJwt = $this->encodeJwt(['account_id' => $accountId]);
        return $this->buildTokenResponse($tokenData, $newJwt);
    }

    /**
     * Формирует JSON-ответ с токенами и установленными куки.
     */
    private function buildTokenResponse(array $tokenData, string $jwt)
    {
        return response()->json([
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'],
            'expires_in' => $tokenData['expires_in'],
            'jwt' => $jwt,
        ])
            ->withCookie(
                cookie(
                    config('session.cookie_token'),
                    $tokenData['access_token'],
                    43200,
                    '/',
                    config('session.domain'),
                    true,
                    true,
                    false,
                    'None'
                )
            )
            ->withCookie(
                cookie(
                    'oidc_jwt',
                    $jwt,
                    static::JWT_TTL / 86400,
                    '/',
                    config('session.domain'),
                    true,
                    true,
                    false,
                    'None'
                )
            );
    }
}
