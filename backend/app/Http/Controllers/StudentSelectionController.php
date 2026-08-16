<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentSelectionController extends Controller
{
    public const MIN_SELECTIONS = 1;
    public const MAX_SELECTIONS = 6;

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'tag_ids' => $request->user()->tagPreferences()->orderBy('priority')->pluck('tag_id'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        if (! AppSetting::isSelectionPhase()) {
            return response()->json(['message' => __('messages.selection_only_during_selection_phase')], 403);
        }

        $validated = $request->validate([
            'tag_ids' => ['required', 'array', 'min:' . self::MIN_SELECTIONS, 'max:' . self::MAX_SELECTIONS],
            'tag_ids.*' => ['required', 'integer', 'distinct', 'exists:tags,id'],
        ]);

        DB::transaction(function () use ($request, $validated) {
            $request->user()->tagPreferences()->delete();
            foreach ($validated['tag_ids'] as $index => $tagId) {
                $request->user()->tagPreferences()->create(['tag_id' => $tagId, 'priority' => $index + 1]);
            }
        });

        return response()->json([
            'tag_ids' => $request->user()->tagPreferences()->orderBy('priority')->pluck('tag_id'),
        ]);
    }
}
