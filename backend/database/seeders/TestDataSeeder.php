<?php

namespace Database\Seeders;

use App\Models\ConsultantProfile;
use App\Models\Series;
use App\Models\SlotOption;
use App\Models\Tag;
use App\Models\TimeSlot;
use App\Models\Topic;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class TestDataSeeder extends Seeder
{
    private const CONFERENCE_DATE = '2026-10-15';

    private array $rooms = ['R101', 'R102', 'R103', 'R201', 'R202', 'Amphi A', 'Amphi B'];

    public function run(): void
    {
        $tags = $this->createTags();
        $series = $this->createSeries();
        $slotTimes = $this->slotTimes();
        $this->createConsultants($tags, $series, $slotTimes);
        $this->createStudents();
    }

    /**
     * Maps every consultant-selectable slot ID (derived from the admin-editable
     * SlotOption list, same scheme as SlotOption::validSlotIds()) to its [start, end] times.
     * Presentation slots are in-person only (video conference participation is no
     * longer allowed).
     *
     * @return Collection<string, array{0: string, 1: string}>
     */
    private function slotTimes(): Collection
    {
        return SlotOption::all()->flatMap(fn (SlotOption $option) => $option->kind === SlotOption::KIND_PRESENTATION
            ? ["in_person_{$option->id}" => [$option->start_time, $option->end_time]]
            : ["reception_{$option->id}" => [$option->start_time, $option->end_time]]
        );
    }

    /** @return Series[] */
    private function createSeries(): array
    {
        $names = ['S', 'ES', 'L', 'STI2D', 'STMG', 'autre'];

        return array_map(
            fn ($name) => Series::firstOrCreate(['name' => $name]),
            $names
        );
    }

    /** @return Tag[] */
    private function createTags(): array
    {
        $definitions = [
            ['name' => 'Computer Science', 'slug' => 'computer-science',  'description' => 'Software development, data science, AI, and IT careers'],
            ['name' => 'Medicine',          'slug' => 'medicine',          'description' => 'Medical school, residency, and healthcare careers'],
            ['name' => 'Law',               'slug' => 'law',               'description' => 'Legal studies, bar exam, and legal careers'],
            ['name' => 'Business',          'slug' => 'business',          'description' => 'Management, entrepreneurship, and consulting'],
            ['name' => 'Engineering',       'slug' => 'engineering',       'description' => 'Mechanical, civil, electrical, and chemical engineering'],
            ['name' => 'Architecture',      'slug' => 'architecture',      'description' => 'Urban planning, design, and building architecture'],
            ['name' => 'Education',         'slug' => 'education',         'description' => 'Teaching, academic research, and educational policy'],
            ['name' => 'Finance',           'slug' => 'finance',           'description' => 'Banking, investment, and financial analysis'],
            ['name' => 'Arts & Media',      'slug' => 'arts-media',        'description' => 'Film, journalism, graphic design, and performing arts'],
            ['name' => 'Environment',       'slug' => 'environment',       'description' => 'Environmental science, sustainability, and ecology'],
        ];

        return array_map(
            fn ($d) => Tag::firstOrCreate(['slug' => $d['slug']], $d),
            $definitions
        );
    }

    /**
     * @param Tag[] $tags
     * @param Series[] $series
     * @param Collection<string, array{0: string, 1: string}> $slotTimes
     */
    private function createConsultants(array $tags, array $series, Collection $slotTimes): void
    {
        $templates = [
            ['slug' => 'computer-science', 'title' => 'Software Engineer at a Startup',      'description' => 'How I built my career in tech after graduating from DFG.'],
            ['slug' => 'computer-science', 'title' => 'Data Science in Practice',             'description' => 'Working with machine-learning models and data pipelines every day.'],
            ['slug' => 'computer-science', 'title' => 'From Student to CTO',                  'description' => 'My entrepreneurial journey in the tech industry.'],
            ['slug' => 'medicine',         'title' => 'Life as a Medical Intern',              'description' => 'The reality of hospital rotations and what residency is really like.'],
            ['slug' => 'medicine',         'title' => 'Choosing a Speciality',                'description' => 'How I decided on cardiology after my sixth year of medical school.'],
            ['slug' => 'law',              'title' => 'Corporate Law at a Big Firm',          'description' => 'Day-to-day life at an international law firm in Paris.'],
            ['slug' => 'law',              'title' => 'Becoming a Judge',                      'description' => 'My path through the magistracy exam and the ENM.'],
            ['slug' => 'business',         'title' => 'Management Consulting Explained',      'description' => 'What consultants actually do — beyond the PowerPoints.'],
            ['slug' => 'business',         'title' => 'Entrepreneurship After the Bac',       'description' => 'Starting a company while studying, and what I wish I had known.'],
            ['slug' => 'business',         'title' => 'MBA or Not?',                          'description' => 'How I evaluated a master\'s in management against working directly.'],
            ['slug' => 'engineering',      'title' => 'Civil Engineering Projects',           'description' => 'Building bridges and public infrastructure across Europe.'],
            ['slug' => 'engineering',      'title' => 'Aeronautical Engineering at Airbus',   'description' => 'My path from preparatory classes to a career in aviation.'],
            ['slug' => 'engineering',      'title' => 'Renewable Energy Engineering',         'description' => 'Designing solar farms and wind turbines in the energy transition.'],
            ['slug' => 'architecture',     'title' => 'Urban Design in a Major City',         'description' => 'Working on city planning projects and public spaces.'],
            ['slug' => 'architecture',     'title' => 'From Architecture School to the Studio','description' => 'Getting your first commission and surviving the first years.'],
            ['slug' => 'education',        'title' => 'Teaching at a University',             'description' => 'Combining research, lecturing, and supervising students.'],
            ['slug' => 'finance',          'title' => 'Investment Banking Analyst',           'description' => 'The hours, the deals, and the culture at a bulge-bracket bank.'],
            ['slug' => 'arts-media',       'title' => 'Documentary Film-making',              'description' => 'From script to festival premiere — a filmmaker\'s journey.'],
            ['slug' => 'environment',      'title' => 'Environmental Consulting',             'description' => 'Advising companies on their carbon footprint and sustainability strategy.'],
            ['slug' => 'finance',          'title' => 'Portfolio Management',                 'description' => 'How I analyse equities and fixed income in an asset management firm.'],
        ];

        $tagsBySlug = collect($tags)->keyBy('slug');
        $allSlotIds = $slotTimes->keys()->all();

        foreach ($templates as $template) {
            $tag  = $tagsBySlug->get($template['slug']) ?? $tags[0];
            $user = User::factory()->create([
                'role'               => User::ROLE_CONSULTANT,
                'email_verified_at'  => now(),
            ]);

            $nameParts = explode(' ', $user->name, 2);
            ConsultantProfile::create([
                'user_id'             => $user->id,
                'first_name'          => $nameParts[0],
                'last_name'           => $nameParts[1] ?? '',
                'graduation_year'     => fake()->numberBetween(2010, 2023),
                'serie'               => fake()->randomElement($series)->name,
                'career_path'         => fake()->paragraph(3),
                'current_situation'   => fake()->sentence(),
                'why_this_career'     => fake()->paragraph(2),
                'consent_poster'      => true,
                'consent_alumni_data' => true,
            ]);

            $shuffled      = collect($allSlotIds)->shuffle()->values()->all();
            $selectedSlots = array_slice($shuffled, 0, fake()->numberBetween(1, 3));

            $topic = Topic::create([
                'title'          => $template['title'],
                'description'    => $template['description'],
                'consultant_id'  => $user->id,
                'tag_id'         => $tag->id,
                'selected_slots' => $selectedSlots,
            ]);

            foreach ($selectedSlots as $slotId) {
                [$start, $end] = $slotTimes[$slotId];
                TimeSlot::create([
                    'topic_id'      => $topic->id,
                    'consultant_id' => $user->id,
                    'starts_at'     => Carbon::parse(self::CONFERENCE_DATE . ' ' . $start),
                    'ends_at'       => Carbon::parse(self::CONFERENCE_DATE . ' ' . $end),
                    'room'          => $this->rooms[array_rand($this->rooms)],
                    'capacity'      => fake()->randomElement([15, 20, 25, 30]),
                ]);
            }
        }
    }

    private function createStudents(): void
    {
        User::factory(30)->create([
            'role'              => User::ROLE_STUDENT,
            'email_verified_at' => now(),
        ]);
    }
}
