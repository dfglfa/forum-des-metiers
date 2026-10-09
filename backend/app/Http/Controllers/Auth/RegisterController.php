<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\AdminInviteController;
use App\Http\Controllers\Controller;
use App\Models\ConsultantProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RegisterController extends Controller
{
    /**
     * Public self-registration for speakers (consultants). This is the "general
     * registration" counterpart to the admin's invitation flow
     * (AdminInviteController) — it captures the same name/salutation/language
     * fields up front so a self-registered account looks identical to an
     * invited-and-activated one, just without an admin having to send an
     * invitation first. Students never self-register here — they always log in
     * via LDAP username or a school-provisioned account.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'salutation' => ['required', 'string', Rule::in(AdminInviteController::SALUTATIONS)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'email'      => ['required', 'email', 'unique:users,email'],
            'password'   => ['required', 'string', 'min:8', 'confirmed'],
            'language'   => ['required', 'string', Rule::in(AdminInviteController::LANGUAGES)],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name'     => $validated['first_name'] . ' ' . $validated['last_name'],
                'email'    => $validated['email'],
                'password' => $validated['password'],
                'role'     => User::ROLE_CONSULTANT,
            ]);

            ConsultantProfile::create([
                'user_id'    => $user->id,
                'salutation' => $validated['salutation'],
                'language'   => $validated['language'],
                'first_name' => $validated['first_name'],
                'last_name'  => $validated['last_name'],
            ]);

            return $user;
        });

        $user->sendEmailVerificationNotification($validated['language']);

        return response()->json(['message' => __('messages.verification_email_sent')], 201);
    }
}
