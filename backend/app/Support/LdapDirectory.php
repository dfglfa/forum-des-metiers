<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use LdapRecord\Auth\BindException;
use LdapRecord\Connection;
use LdapRecord\Container;

/**
 * Authenticates users against the configured directory by first looking up their real DN
 * (sAMAccountName for Active Directory, uid for OpenLDAP) and then binding with it, instead of
 * guessing a DN pattern that only fits some directory layouts.
 */
class LdapDirectory
{
    /**
     * Returns the user's attributes (lower-cased keys, single values, including "dn") when the
     * password is correct, or null when the user is unknown or the password is rejected.
     * Throws when the directory cannot be reached.
     *
     * @return array<string, mixed>|null
     */
    public function authenticate(string $username, string $password): ?array
    {
        // An empty password would be treated as an anonymous bind and always succeed.
        if ($username === '' || $password === '') {
            return null;
        }

        $user = $this->findUser($username);

        if ($user === null || empty($user['dn'])) {
            Log::info('LDAP: no entry found for user', ['username' => $username]);

            return null;
        }

        /** @var Connection $connection */
        $connection = Container::getDefaultConnection();

        // bind() instead of attempt(): attempt() swallows the reason for a failed bind, but we want
        // the LDAP error code (49 = wrong password, -1 = server unreachable, ...) in the log.
        try {
            $connection->auth()->bind($user['dn'], $password);
        } catch (BindException $e) {
            $context = ['username' => $username, 'dn' => $user['dn']] + $this->describe($e);

            if ($context['ldap_code'] === 49) {
                Log::info('LDAP: user bind rejected (invalid credentials)', $context);

                return null;
            }

            Log::error('LDAP: user bind failed for a reason other than invalid credentials', $context);

            throw $e;
        }

        Log::info('LDAP: user authenticated', ['username' => $username, 'dn' => $user['dn']]);

        return $user;
    }

    /**
     * Connection settings without secrets, for diagnosing "why did we fall back to local login".
     *
     * @return array<string, mixed>
     */
    public function connectionSummary(): array
    {
        $config = config('ldap.connections.default');

        return [
            'hosts' => $config['hosts'],
            'port' => $config['port'],
            'base_dn' => $config['base_dn'],
            'bind_dn' => $config['username'],
            'timeout' => $config['timeout'],
            'tls' => $config['use_tls'],
            'starttls' => $config['use_starttls'],
            'ldap_extension_loaded' => extension_loaded('ldap'),
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'php_ini' => php_ini_loaded_file(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function describe(\Throwable $e): array
    {
        $context = [
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'ldap_code' => $e->getCode(),
        ];

        if ($e instanceof \LdapRecord\LdapRecordException && ($detailed = $e->getDetailedError())) {
            $context['ldap_code'] = $detailed->getErrorCode();
            $context['ldap_error'] = $detailed->getErrorMessage();
            $context['ldap_diagnostic'] = $detailed->getDiagnosticMessage();
        }

        return $context;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findUser(string $username): ?array
    {
        /** @var Connection $connection */
        $connection = Container::getDefaultConnection();

        // Searching binds as the configured service account (LDAP_USERNAME/LDAP_PASSWORD); this is
        // where unreachable hosts, TLS problems and a wrong service password surface.
        try {
            $result = $connection->query()
                ->in(config('ldap.connections.default.base_dn'))
                ->whereEquals('sAMAccountName', $username)
                ->orWhereEquals('uid', $username)
                ->first();
        } catch (\Throwable $e) {
            Log::error('LDAP: directory search failed (connect/service bind/search)', [
                'username' => $username,
            ] + $this->describe($e) + $this->connectionSummary());

            throw $e;
        }

        if (! is_array($result)) {
            return null;
        }

        $flat = [];
        foreach ($result as $key => $value) {
            $flat[strtolower((string) $key)] = is_array($value) ? ($value[0] ?? null) : $value;
        }

        return $flat;
    }
}
