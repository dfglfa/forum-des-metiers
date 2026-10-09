<?php

use App\Models\Topic;
use Illuminate\Database\Migrations\Migration;

/**
 * Video-conference participation was removed: SlotOption::validSlotIds() no
 * longer emits "video_{id}" ids. Any topic that still has such an id stored in
 * its selected_slots (from before this change) is migrated to the matching
 * "in_person_{id}" slot instead of silently losing that time preference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Topic::query()->whereNotNull('selected_slots')->get()->each(function (Topic $topic) {
            $slots = $topic->selected_slots ?? [];

            $migrated = array_values(array_unique(array_map(
                fn (string $slot) => str_starts_with($slot, 'video_')
                    ? 'in_person_' . substr($slot, strlen('video_'))
                    : $slot,
                $slots
            )));

            if ($migrated !== $slots) {
                $topic->selected_slots = $migrated;
                $topic->save();
            }
        });
    }

    public function down(): void
    {
        // Irreversible: original video/in-person distinction is not recoverable.
    }
};
