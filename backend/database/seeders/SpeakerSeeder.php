<?php

namespace Database\Seeders;

use App\Http\Controllers\AdminInviteController;
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

/**
 * Standalone seeder that fills the database with ~30 speakers (consultants),
 * independent of TestDataSeeder. Roughly a fifth are left in the "invited but
 * not yet activated" state (mirroring AdminInviteController::createAndInviteSpeaker:
 * email_verified_at null, a sparse profile, no talk yet) so the admin speakers
 * overview has realistic pending/activated and DE/FR mixes to sort/filter at scale.
 *
 * Run with: php artisan db:seed --class=SpeakerSeeder
 */
class SpeakerSeeder extends Seeder
{
    private const CONFERENCE_DATE = '2026-10-15';
    private const PENDING_EVERY_NTH = 5;

    private array $rooms = ['R101', 'R102', 'R103', 'R201', 'R202', 'Amphi A', 'Amphi B'];

    /** @var array<int, array{slug: string, title: string, description: string}> */
    private array $templates = [
        ['slug' => 'computer-science', 'title' => 'Ingénieur logiciel dans une start-up',        'description' => 'Comment j\'ai construit ma carrière dans la tech après le bac.'],
        ['slug' => 'computer-science', 'title' => 'Data Scientist bei einem Versicherungskonzern','description' => 'Wie ich mit Machine-Learning-Modellen und Datenpipelines arbeite.'],
        ['slug' => 'computer-science', 'title' => 'Cybersécurité au quotidien',                   'description' => 'Protéger les systèmes d\'une grande entreprise contre les attaques.'],
        ['slug' => 'medicine',         'title' => 'Assistenzarzt in der Notaufnahme',             'description' => 'Der Alltag zwischen Nachtschichten und Weiterbildung.'],
        ['slug' => 'medicine',         'title' => 'Sage-femme en maternité',                      'description' => 'Accompagner les familles avant, pendant et après la naissance.'],
        ['slug' => 'medicine',         'title' => 'Zahnärztin mit eigener Praxis',                'description' => 'Der Weg vom Studium zur eigenen Praxisgründung.'],
        ['slug' => 'law',              'title' => 'Avocate en droit des affaires',                'description' => 'Le quotidien dans un cabinet international à Strasbourg.'],
        ['slug' => 'law',              'title' => 'Notar in eigener Kanzlei',                     'description' => 'Immobilien, Erbrecht und der Weg zum Notariat.'],
        ['slug' => 'business',         'title' => 'Consultante en stratégie',                     'description' => 'Ce que fait vraiment un consultant, au-delà des présentations.'],
        ['slug' => 'business',         'title' => 'Gründerin eines E-Commerce-Start-ups',         'description' => 'Von der Schulbank zur eigenen Firma — was ich unterwegs gelernt habe.'],
        ['slug' => 'business',         'title' => 'Responsable marketing international',          'description' => 'Lancer des produits sur plusieurs marchés européens à la fois.'],
        ['slug' => 'engineering',      'title' => 'Bauingenieurin auf Großbaustellen',            'description' => 'Brücken und Infrastrukturprojekte in ganz Europa planen.'],
        ['slug' => 'engineering',      'title' => 'Ingénieur aéronautique',                       'description' => 'Du bac au bureau d\'études, en passant par la prépa.'],
        ['slug' => 'engineering',      'title' => 'Elektroingenieur in der Automobilindustrie',   'description' => 'Steuergeräte und Software für die Fahrzeuge von morgen.'],
        ['slug' => 'architecture',     'title' => 'Architecte urbaniste',                         'description' => 'Concevoir des quartiers entiers, entre contraintes et créativité.'],
        ['slug' => 'architecture',     'title' => 'Innenarchitektin',                             'description' => 'Vom ersten Entwurf bis zur fertigen Raumgestaltung.'],
        ['slug' => 'education',        'title' => 'Professeure des écoles',                       'description' => 'Le quotidien en classe et la formation pour devenir enseignante.'],
        ['slug' => 'education',        'title' => 'Dozent an einer Hochschule',                   'description' => 'Forschung, Lehre und die Betreuung von Studierenden verbinden.'],
        ['slug' => 'finance',          'title' => 'Analyste en banque d\'investissement',         'description' => 'Les horaires, les deals et la culture d\'une grande banque.'],
        ['slug' => 'finance',          'title' => 'Finanzberaterin',                              'description' => 'Kunden auf dem Weg zu ihren finanziellen Zielen begleiten.'],
        ['slug' => 'finance',          'title' => 'Contrôleur de gestion',                        'description' => 'Piloter la performance financière d\'un groupe industriel.'],
        ['slug' => 'arts-media',       'title' => 'Journalistin bei einer Tageszeitung',          'description' => 'Recherche, Interviews und der Druck der täglichen Deadline.'],
        ['slug' => 'arts-media',       'title' => 'Réalisatrice de documentaires',                'description' => 'Du scénario à la première projection en festival.'],
        ['slug' => 'arts-media',       'title' => 'Grafikdesigner in einer Agentur',              'description' => 'Von der Skizze zum fertigen Corporate Design.'],
        ['slug' => 'environment',      'title' => 'Ingénieure en environnement',                  'description' => 'Conseiller les entreprises sur leur empreinte carbone.'],
        ['slug' => 'environment',      'title' => 'Klimaschutzmanagerin einer Stadtverwaltung',   'description' => 'Kommunale Klimaprojekte planen und umsetzen.'],
        ['slug' => 'environment',      'title' => 'Agronome en agriculture durable',              'description' => 'Accompagner les exploitations vers des pratiques plus durables.'],
        ['slug' => 'computer-science', 'title' => 'Product Managerin in der Softwarebranche',    'description' => 'Zwischen Kundenbedürfnissen, Entwicklung und Roadmap.'],
        ['slug' => 'business',         'title' => 'Chargé de recrutement en cabinet RH',          'description' => 'Accompagner les entreprises et les candidats vers le bon poste.'],
        ['slug' => 'medicine',         'title' => 'Physiotherapeut in eigener Praxis',            'description' => 'Vom Sportstudium zur selbstständigen Praxisführung.'],
    ];

    public function run(): void
    {
        $tags = $this->ensureTags();
        $series = $this->ensureSeries();
        $slotTimes = $this->slotTimes();

        $tagsBySlug = collect($tags)->keyBy('slug');
        $allSlotIds = $slotTimes->keys()->all();

        foreach ($this->templates as $index => $template) {
            $pending = ($index + 1) % self::PENDING_EVERY_NTH === 0;
            $language = fake()->randomElement(AdminInviteController::LANGUAGES);
            $salutation = fake()->randomElement(AdminInviteController::SALUTATIONS);
            $firstName = fake()->firstName();
            $lastName = fake()->lastName();

            $user = User::factory()->create([
                'name'              => "{$firstName} {$lastName}",
                'role'              => User::ROLE_CONSULTANT,
                'email_verified_at' => $pending ? null : now(),
            ]);

            if ($pending) {
                ConsultantProfile::create([
                    'user_id'    => $user->id,
                    'salutation' => $salutation,
                    'language'   => $language,
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                ]);

                continue;
            }

            ConsultantProfile::create([
                'user_id'             => $user->id,
                'salutation'          => $salutation,
                'language'            => $language,
                'first_name'          => $firstName,
                'last_name'           => $lastName,
                'graduation_year'     => fake()->numberBetween(2005, 2023),
                'serie'               => fake()->randomElement($series)->name,
                'career_path'         => fake()->paragraph(3),
                'current_situation'   => fake()->sentence(),
                'why_this_career'     => fake()->paragraph(2),
                'consent_poster'      => true,
                'consent_alumni_data' => true,
            ]);

            $tag = $tagsBySlug->get($template['slug']) ?? $tags[0];

            $selectedSlots = collect($allSlotIds)->shuffle()->take(fake()->numberBetween(1, 3))->values()->all();

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

    /** @return Tag[] */
    private function ensureTags(): array
    {
        $definitions = [
            ['name' => 'Computer Science', 'slug' => 'computer-science', 'description' => 'Software development, data science, AI, and IT careers'],
            ['name' => 'Medicine',         'slug' => 'medicine',         'description' => 'Medical school, residency, and healthcare careers'],
            ['name' => 'Law',              'slug' => 'law',              'description' => 'Legal studies, bar exam, and legal careers'],
            ['name' => 'Business',         'slug' => 'business',         'description' => 'Management, entrepreneurship, and consulting'],
            ['name' => 'Engineering',      'slug' => 'engineering',      'description' => 'Mechanical, civil, electrical, and chemical engineering'],
            ['name' => 'Architecture',     'slug' => 'architecture',     'description' => 'Urban planning, design, and building architecture'],
            ['name' => 'Education',        'slug' => 'education',        'description' => 'Teaching, academic research, and educational policy'],
            ['name' => 'Finance',          'slug' => 'finance',          'description' => 'Banking, investment, and financial analysis'],
            ['name' => 'Arts & Media',     'slug' => 'arts-media',       'description' => 'Film, journalism, graphic design, and performing arts'],
            ['name' => 'Environment',      'slug' => 'environment',      'description' => 'Environmental science, sustainability, and ecology'],
        ];

        return array_map(
            fn ($d) => Tag::firstOrCreate(['slug' => $d['slug']], $d),
            $definitions
        );
    }

    /** @return Series[] */
    private function ensureSeries(): array
    {
        $names = ['S', 'ES', 'L', 'STI2D', 'STMG', 'autre'];

        return array_map(
            fn ($name) => Series::firstOrCreate(['name' => $name]),
            $names
        );
    }

    /**
     * Maps every consultant-selectable slot ID (derived from the admin-editable
     * SlotOption list) to its [start, end] times. Presentation slots are
     * in-person only (video conference participation is no longer allowed).
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
}
