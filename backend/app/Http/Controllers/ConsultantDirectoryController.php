<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ConsultantDirectoryController extends Controller
{
    public function index(): JsonResponse
    {
        // Only name, picture and graduation year are exposed — no talk details. Consultants
        // marked "not participating" are hidden.
        $consultants = User::where('role', User::ROLE_CONSULTANT)
            ->where('is_participating', true)
            ->with('consultantProfile')
            ->get()
            ->map(function (User $user) {
                $profile = $user->consultantProfile;

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'last_name' => $profile?->last_name,
                    'first_name' => $profile?->first_name,
                    'graduation_year' => $profile?->graduation_year,
                    'profile_picture_url' => $profile?->profile_picture_url,
                ];
            });

        // Last name, then first name.
        $key = fn (?string $s) => Str::lower(Str::ascii($s ?? ''));
        $sorted = $consultants->sortBy([
            fn ($a, $b) => strcmp($key($a['last_name'] ?? $a['name']), $key($b['last_name'] ?? $b['name'])),
            fn ($a, $b) => strcmp($key($a['first_name']), $key($b['first_name'])),
        ]);

        return response()->json(
            $sorted->map(fn ($c) => collect($c)->except(['last_name', 'first_name'])->all())->values()
        );
    }
}
