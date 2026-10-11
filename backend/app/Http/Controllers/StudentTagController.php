<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\JsonResponse;

class StudentTagController extends Controller
{
    public function index(): JsonResponse
    {
        // Topics of consultants who were marked "not participating" (e.g. illness) by the
        // admin are excluded here, so students never pick a talk that won't take place.
        // The consultant's own account/profile/topic data stays untouched in the database.
        $onlyParticipating = function ($query) {
            $query->whereHas('consultant', fn ($q) => $q->where('is_participating', true));
        };

        return response()->json(
            Tag::withCount(['topics' => $onlyParticipating])
                ->with([
                    'topics' => function ($query) use ($onlyParticipating) {
                        $onlyParticipating($query);
                        $query->select(['id', 'title', 'tag_id', 'consultant_id']);
                    },
                    'topics.consultant:id,name,role',
                    'topics.consultant.consultantProfile',
                ])
                ->orderBy('name')
                ->get()
        );
    }
}
