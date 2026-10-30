<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;

class ConsultantDirectoryController extends Controller
{
    public function index(): JsonResponse
    {
        // Only name, picture and graduation year are exposed — no talk details. Consultants
        // marked "not participating" are hidden.
        $consultants = User::where('role', User::ROLE_CONSULTANT)
            ->where('is_participating', true)
            ->with('consultantProfile')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'graduation_year' => $user->consultantProfile?->graduation_year,
                'profile_picture_url' => $user->consultantProfile?->profile_picture_url,
            ]);

        return response()->json($consultants->values());
    }
}
