<?php

namespace App\Support;

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
            return null;
        }

        /** @var Connection $connection */
        $connection = Container::getDefaultConnection();

        return $connection->auth()->attempt($user['dn'], $password) ? $user : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findUser(string $username): ?array
    {
        /** @var Connection $connection */
        $connection = Container::getDefaultConnection();

        $result = $connection->query()
            ->in(config('ldap.connections.default.base_dn'))
            ->whereEquals('sAMAccountName', $username)
            ->orWhereEquals('uid', $username)
            ->first();

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
