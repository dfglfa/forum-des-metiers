<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use Illuminate\Http\JsonResponse;

class StudentTopicController extends Controller
{
    public function index(): JsonResponse
    {
        // Exclude topics belonging to consultants marked "not participating" (e.g. illness),
        // so students never see/select a talk that won't actually happen. The consultant's
        // account/profile/topic data itself is left intact for next year.
        return response()->json(
            Topic::with(['tag', 'timeSlots', 'consultant.consultantProfile'])
                ->whereHas('consultant', fn ($q) => $q->where('is_participating', true))
                ->get()
        );
    }
}
