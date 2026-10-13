<?php

namespace Tests\Concerns;

use LdapRecord\Testing\LdapFake;

trait FakesLdapUsers
{
    /**
     * Makes the faked directory return one user for the lookup search. The DN deliberately has an
     * Active-Directory shape (CN=…,OU=…) rather than uid=…, to prove it is not guessed from the username.
     */
    protected function fakeLdapUser(LdapFake $ldap, string $username, array $attributes = []): string
    {
        $dn = "CN=Full {$username},OU=Users," . config('ldap.connections.default.base_dn');

        $entry = array_merge(['dn' => $dn, 'samaccountname' => [$username], 'cn' => ["Full {$username}"]], $attributes);

        $raw = [];
        foreach ($entry as $key => $value) {
            $raw[$key] = $key === 'dn' ? $value : array_merge(['count' => count((array) $value)], array_values((array) $value));
        }
        $raw['count'] = count($raw);

        $ldap->expect(LdapFake::operation('search')->andReturn([$raw, 'count' => 1]));

        return $dn;
    }
}
