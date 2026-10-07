<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use LdapRecord\Connection;
use LdapRecord\Container;

class StudentLoginController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $username = $request->input('username');
        $password = $request->input('password');

        if (AppSetting::getBool('ldap_students', true)) {
            $response = $this->attemptLdapLogin($username, $password);

            if ($response !== null) {
                return $response;
            }

            // $response === null means LDAP itself couldn't be reached (as opposed to reaching it
            // and having the bind rejected) — fall through to the local password below instead of
            // locking every student out whenever the directory server is down.
        }

        return $this->loginViaLocalPassword($username, $password);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('messages.logged_out')]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    /**
     * Returns the login response once LDAP was actually reachable, whether the bind succeeded or
     * the credentials were rejected. Returns null only when LDAP itself could not be contacted.
     */
    private function attemptLdapLogin(string $username, string $password): ?JsonResponse
    {
        try {
            $authenticated = $this->authenticateViaLdap($username, $password);
        } catch (\Throwable $e) {
            logger()->warning('LDAP unreachable during student login, falling back to local password: ' . $e->getMessage());

            return null;
        }

        if (! $authenticated) {
            throw ValidationException::withMessages([
                'username' => [__('messages.credentials_incorrect')],
            ]);
        }

        return $this->completeLdapLogin($username);
    }

    private function completeLdapLogin(string $username): JsonResponse
    {
        $ldapUser = $this->findLdapUser($username);

        $user = User::firstOrCreate(
            ['ldap_username' => $username],
            [
                'name' => $ldapUser['displayName'] ?? $ldapUser['cn'] ?? $username,
                'first_name' => $ldapUser['givenname'] ?? null,
                'last_name' => $ldapUser['sn'] ?? null,
                'email' => $ldapUser['mail'] ?? null,
                'role' => User::ROLE_STUDENT,
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

        $user->update([
            'name' => $ldapUser['displayName'] ?? $ldapUser['cn'] ?? $username,
            'first_name' => $ldapUser['givenname'] ?? $user->first_name,
            'last_name' => $ldapUser['sn'] ?? $user->last_name,
            'email' => $ldapUser['mail'] ?? $user->email,
        ]);
        $user->recordLogin();

        $token = $user->createToken('student-token', ['role:student'])->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user]);
    }

    /**
     * Used when LDAP is disabled for students or unreachable. Only attempted for passwords of at
     * least 8 characters — every local password in this app is created under that minimum (see
     * AdminStudentImportController), so a shorter input can never be a valid match.
     */
    private function loginViaLocalPassword(string $username, string $password): JsonResponse
    {
        if (strlen($password) >= 8) {
            $user = User::where('ldap_username', $username)
                ->where('role', User::ROLE_STUDENT)
                ->whereNotNull('password')
                ->first();

            if ($user && Hash::check($password, $user->password)) {
                $user->recordLogin();

                $token = $user->createToken('student-token', ['role:student'])->plainTextToken;

                return response()->json(['token' => $token, 'user' => $user]);
            }
        }

        throw ValidationException::withMessages([
            'username' => [__('messages.credentials_incorrect')],
        ]);
    }

    private function authenticateViaLdap(string $username, string $password): bool
    {
        /** @var Connection $connection */
        $connection = Container::getDefaultConnection();
        $userDn = $this->buildUserDn($username);

        return $connection->auth()->attempt($userDn, $password);
    }

    private function findLdapUser(string $username): array
    {
        try {
            /** @var Connection $connection */
            $connection = Container::getDefaultConnection();
            $baseDn = config('ldap.connections.default.base_dn');
            $result = $connection->query()
                ->in($baseDn)
                ->whereEquals('sAMAccountName', $username)
                ->orWhereEquals('uid', $username)
                ->firstOrFail();

            return array_map(fn ($v) => is_array($v) ? ($v[0] ?? null) : $v, $result);
        } catch (\Exception) {
            return [];
        }
    }

    private function buildUserDn(string $username): string
    {
        $baseDn = config('ldap.connections.default.base_dn');
        return "uid={$username},{$baseDn}";
    }
}
