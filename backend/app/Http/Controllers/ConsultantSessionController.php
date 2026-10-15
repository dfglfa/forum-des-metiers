<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\SlotOption;
use App\Models\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConsultantSessionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->topics()->with('tag', 'timeSlots')->first()
        );
    }

    public function update(Request $request): JsonResponse
    {
        if (AppSetting::isConferencePhase()) {
            return response()->json(['message' => __('messages.session_locked_during_conference')], 403);
        }

        $validated = $request->validate([
            'title'          => ['sometimes', 'required', 'string', 'max:255'],
            'description'    => ['sometimes', 'nullable', 'string', 'max:200'],
            'selected_slots' => ['required', 'array', 'min:1'],
            'selected_slots.*' => ['required', 'string', Rule::in(SlotOption::validSlotIds())],
        ]);

        // Speakers no longer enter a title/description; the title column is NOT NULL, so a topic
        // created without one falls back to the speaker's name.
        $topic = Topic::firstOrNew(['consultant_id' => $request->user()->id]);
        if (! $topic->exists) {
            $topic->title = $request->user()->name;
        }
        $topic->fill($validated)->save();

        return response()->json($topic->fresh(['tag', 'timeSlots']));
    }
}
