<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminConsultantParticipationController extends Controller
{
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'is_participating' => ['required', 'boolean'],
        ]);

        $consultant = User::where('role', User::ROLE_CONSULTANT)->findOrFail($id);
        $consultant->update($validated);

        return response()->json($consultant->fresh());
    }
}
