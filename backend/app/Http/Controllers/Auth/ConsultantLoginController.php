<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\User;
use App\Support\LdapDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ConsultantLoginController extends Controller
{
    public function __construct(private readonly LdapDirectory $ldap)
    {
    }

    public function login(Request $request): JsonResponse
    {
        return AppSetting::getBool('ldap_consultants')
            ? $this->loginViaLdap($request)
            : $this->loginViaPassword($request);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('messages.logged_out')]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->load('consultantProfile'));
    }

    private function loginViaPassword(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => [__('messages.credentials_incorrect')],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        // Admins have their own dedicated login (/auth/admin/login) — this endpoint is
        // consultant-only, even though an admin's credentials would otherwise pass Auth::attempt.
        if (! $user->isConsultant()) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => [__('messages.consultant_login_only')],
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => [__('messages.verify_email_before_login')],
            ]);
        }

        $user->recordLogin();

        $token = $user->createToken('consultant-token', ['role:consultant'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->load('consultantProfile'),
        ]);
    }

    private function loginViaLdap(Request $request): JsonResponse
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $username = $request->input('username');
        $password = $request->input('password');

        $ldapUser = $this->authenticateViaLdap($username, $password);

        if ($ldapUser === null) {
            throw ValidationException::withMessages([
                'username' => [__('messages.credentials_incorrect')],
            ]);
        }

        $user = User::firstOrCreate(
            ['ldap_username' => $username],
            [
                'name' => $ldapUser['displayname'] ?? $ldapUser['cn'] ?? $username,
                'email' => $ldapUser['mail'] ?? null,
                'role' => User::ROLE_CONSULTANT,
                'password' => null,
                'email_verified_at' => now(),
            ]
        );

        // Hard invariant: admin accounts must never be authenticated via LDAP, even if a row somehow
        // matched (e.g. an ldap_username set on an admin through some future/other code path).
        if ($user->isAdmin()) {
            throw ValidationException::withMessages([
                'username' => [__('messages.admin_must_use_email_login')],
            ]);
        }

        if (! $user->isConsultant()) {
            throw ValidationException::withMessages([
                'username' => [__('messages.consultant_login_only')],
            ]);
        }

        $user->update([
            'name' => $ldapUser['displayname'] ?? $ldapUser['cn'] ?? $username,
            'email' => $ldapUser['mail'] ?? $user->email,
        ]);
        $user->recordLogin();

        $token = $user->createToken('consultant-token', ['role:consultant'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->load('consultantProfile'),
        ]);
    }

    private function authenticateViaLdap(string $username, string $password): ?array
    {
        try {
            return $this->ldap->authenticate($username, $password);
        } catch (\Exception $e) {
            logger()->error('LDAP auth error', ['username' => $username] + $this->ldap->describe($e));

            return null;
        }
    }
}
