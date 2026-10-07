<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminLdapStudentsController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ldap_students' => ['required', 'boolean'],
        ]);

        AppSetting::set('ldap_students', $validated['ldap_students'] ? 'true' : 'false');

        return response()->json(['ldap_students' => $validated['ldap_students']]);
    }
}
