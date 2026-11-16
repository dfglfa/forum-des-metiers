<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\SimpleXlsx;
use Illuminate\Http\Response;

class AdminConsultantExportController extends Controller
{
    private const LANGUAGES = ['de' => 'Deutsch', 'fr' => 'Französisch', 'de_fr' => 'Deutsch & Französisch'];

    public function __invoke(): Response
    {
        $consultants = User::where('role', User::ROLE_CONSULTANT)
            ->with(['consultantProfile', 'topics.tag'])
            ->orderBy('name')
            ->get();

        $headers = [
            'Name', 'Vorname', 'Nachname', 'Anrede', 'E-Mail', 'Telefon', 'Abijahrgang', 'Zug',
            'Muttersprache', 'LinkedIn', 'Thema (Tag)', 'Vortragstitel', 'Kurzbeschreibung', 'Zeitfenster',
            'Laufbahn nach der Schule', 'Aktuelle Situation', 'Warum diese Laufbahn',
            'Teilnahme', 'Aktiviert', 'Einwilligung Plakat', 'Einwilligung Alumni',
        ];

        $yesNo = fn (?bool $v) => $v ? 'Ja' : 'Nein';

        $rows = $consultants->map(function (User $c) use ($yesNo) {
            $p = $c->consultantProfile;
            $topic = $c->topics->first();
            $lang = $p?->language;

            return [
                $c->name,
                $p?->first_name ?? $c->first_name,
                $p?->last_name ?? $c->last_name,
                $p?->salutation,
                $c->email,
                $p?->phone,
                $p?->graduation_year,
                $p?->serie,
                $lang ? (self::LANGUAGES[$lang] ?? $lang) : null,
                $p?->linkedin_url,
                $topic?->tag?->name,
                $topic?->title,
                $topic?->description,
                $topic ? implode(', ', $topic->selected_slots ?? []) : null,
                $p?->career_path,
                $p?->current_situation,
                $p?->why_this_career,
                $yesNo($c->is_participating),
                $yesNo($c->email_verified_at !== null),
                $yesNo($p?->consent_poster),
                $yesNo($p?->consent_alumni_data),
            ];
        })->all();

        return response(SimpleXlsx::build($headers, $rows, 'Referenten'), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="referenten-'.now()->format('Y-m-d').'.xlsx"',
        ]);
    }
}
