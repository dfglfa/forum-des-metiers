<?php

namespace Tests\Feature;

use App\Models\SlotOption;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemoveVideoSlotSelectionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_video_slot_selections_are_migrated_to_in_person(): void
    {
        $slot = SlotOption::where('kind', SlotOption::KIND_PRESENTATION)->firstOrFail();
        $otherSlot = SlotOption::where('kind', SlotOption::KIND_PRESENTATION)
            ->where('id', '!=', $slot->id)
            ->first();
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);

        // Simulate data persisted before video-conference participation was
        // removed: a legacy video selection, plus one that already clashes
        // with an existing in-person selection for the same slot (should be
        // deduplicated rather than appearing twice).
        $topic = Topic::create([
            'title' => 'Corporate Law',
            'consultant_id' => $consultant->id,
            'selected_slots' => array_values(array_filter([
                "video_{$slot->id}",
                "in_person_{$slot->id}",
                $otherSlot ? "video_{$otherSlot->id}" : null,
            ])),
        ]);

        /** @var \Illuminate\Database\Migrations\Migration $migration */
        $migration = require base_path('database/migrations/2026_10_09_100000_remove_video_slot_selections_from_topics.php');
        $migration->up();

        $expected = array_values(array_unique(array_filter([
            "in_person_{$slot->id}",
            $otherSlot ? "in_person_{$otherSlot->id}" : null,
        ])));

        $this->assertEqualsCanonicalizing($expected, $topic->fresh()->selected_slots);
    }
}
