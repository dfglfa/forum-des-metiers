<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminEventTextsController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'description_de' => 'nullable|string|max:5000',
            'description_fr' => 'nullable|string|max:5000',
        ]);

        AppSetting::set('event_description_de', $validated['description_de'] ?? '');
        AppSetting::set('event_description_fr', $validated['description_fr'] ?? '');

        return response()->json([
            'event_description' => [
                'de' => $validated['description_de'] ?? '',
                'fr' => $validated['description_fr'] ?? '',
            ],
        ]);
    }
}
