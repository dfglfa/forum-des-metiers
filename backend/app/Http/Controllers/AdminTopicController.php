<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTopicController extends Controller
{
    public function updateTag(Request $request, Topic $topic): JsonResponse
    {
        $validated = $request->validate([
            'tag_id' => ['required', 'integer', 'exists:tags,id'],
        ]);

        $topic->update($validated);

        return response()->json($topic->fresh()->load('tag'));
    }

    /**
     * Tag a consultant who has no topic yet: creates the topic (titled with the consultant's
     * name, like ConsultantSessionController does) and assigns the tag in one step.
     */
    public function storeForConsultant(Request $request, int $id): JsonResponse
    {
        $consultant = User::where('role', User::ROLE_CONSULTANT)->findOrFail($id);

        $validated = $request->validate([
            'tag_id' => ['required', 'integer', 'exists:tags,id'],
        ]);

        $topic = Topic::firstOrNew(['consultant_id' => $consultant->id]);
        if (! $topic->exists) {
            $topic->title = $consultant->name;
            $topic->selected_slots = [];
        }
        $topic->tag_id = $validated['tag_id'];
        $topic->save();

        return response()->json($topic->fresh()->load('tag'));
    }
}
